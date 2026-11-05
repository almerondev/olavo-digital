<?php

namespace App\Modules\Home\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Home\Models\Banner;
use App\Modules\Home\Models\HomeLayout;
use App\Modules\Home\Models\HomeSection;
use App\Support\Audit\Audit;
use App\Support\Http\ApiException;
use Illuminate\Support\Facades\DB;

/** Edições acontecem em um rascunho: os alunos só veem a home depois de publicada. */
class HomeBuilderService
{
    public function __construct(private HomeService $home)
    {
    }

    public function draftFor(string $surface): HomeLayout
    {
        $draft = HomeLayout::where('surface', $surface)->where('status', HomeLayout::DRAFT)->first();

        if ($draft) {
            return $draft;
        }

        $source = HomeLayout::where('surface', $surface)->where('status', HomeLayout::PUBLISHED)->latest('published_at')->first();

        return $source ? $this->copy($source) : HomeLayout::create([
            'name' => 'Rascunho',
            'surface' => $surface,
            'status' => HomeLayout::DRAFT,
        ]);
    }

    public function publish(HomeLayout $layout, User $actor): HomeLayout
    {
        $this->assertDraft($layout);

        DB::transaction(function () use ($layout, $actor) {
            HomeLayout::where('surface', $layout->surface)
                ->where('status', HomeLayout::PUBLISHED)
                ->update(['status' => HomeLayout::ARCHIVED]);

            $layout->update(['status' => HomeLayout::PUBLISHED, 'published_at' => now(), 'published_by' => $actor->id]);
        });

        $this->home->flush();
        Audit::record('home.published', $layout, ['surface' => $layout->surface], $actor);

        return $layout;
    }

    /** Desfaz uma publicação errada: a versão antiga volta como rascunho, para revisar antes de publicar. */
    public function restore(HomeLayout $layout, User $actor): HomeLayout
    {
        if ($layout->status !== HomeLayout::ARCHIVED) {
            throw new ApiException('Só versões arquivadas podem ser restauradas.', 'invalid_transition', 409);
        }

        return DB::transaction(function () use ($layout, $actor) {
            HomeLayout::where('surface', $layout->surface)->where('status', HomeLayout::DRAFT)->delete();

            $draft = $this->copy($layout);
            Audit::record('home.restored', $draft, ['from' => $layout->id], $actor);

            return $draft;
        });
    }

    public function discardDraft(HomeLayout $layout): void
    {
        $this->assertDraft($layout);

        $layout->delete();
    }

    public function assertDraft(HomeLayout $layout): void
    {
        if ($layout->status !== HomeLayout::DRAFT) {
            throw new ApiException('Só o rascunho pode ser alterado.', 'layout_not_draft', 409);
        }
    }

    private function copy(HomeLayout $source): HomeLayout
    {
        return DB::transaction(function () use ($source) {
            $draft = HomeLayout::create(['name' => 'Rascunho', 'surface' => $source->surface, 'status' => HomeLayout::DRAFT]);

            foreach ($source->sections()->with('materials', 'audienceTargets')->get() as $section) {
                $copy = $section->replicate();
                $copy->home_layout_id = $draft->id;
                $copy->save();
                $copy->materials()->sync($section->materials->mapWithKeys(fn ($material) => [$material->id => ['sort_order' => $material->pivot->sort_order]]));
                $this->copyAudience($section, $copy);
            }

            foreach ($source->banners()->with('audienceTargets')->get() as $banner) {
                $copy = $banner->replicate();
                $copy->home_layout_id = $draft->id;
                $copy->save();
                $this->copyAudience($banner, $copy);
            }

            return $draft;
        });
    }

    private function copyAudience(HomeSection|Banner $from, HomeSection|Banner $to): void
    {
        $to->syncAudience(
            $from->audienceTargets->pluck('student_group_id')->filter()->values()->all(),
            $from->audienceTargets->pluck('student_id')->filter()->values()->all(),
        );
    }
}
