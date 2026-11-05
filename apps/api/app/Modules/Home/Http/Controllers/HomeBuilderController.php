<?php

namespace App\Modules\Home\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Models\StudentGroup;
use App\Modules\Home\Http\Requests\SaveBannerRequest;
use App\Modules\Home\Http\Requests\SaveSectionRequest;
use App\Modules\Home\Http\Resources\BannerResource;
use App\Modules\Home\Http\Resources\LayoutResource;
use App\Modules\Home\Http\Resources\SectionResource;
use App\Modules\Home\Models\Banner;
use App\Modules\Home\Models\HomeLayout;
use App\Modules\Home\Models\HomeSection;
use App\Modules\Home\Services\HomeBuilderService;
use App\Modules\Home\Services\HomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HomeBuilderController extends Controller
{
    private const RELATIONS = ['sections.materials', 'sections.audienceTargets', 'banners.image', 'banners.material', 'banners.audienceTargets'];

    public function __construct(private HomeBuilderService $builder, private HomeService $home)
    {
    }

    public function versions(Request $request): JsonResponse
    {
        $surface = $this->surface($request);

        return response()->json(['data' => HomeLayout::where('surface', $surface)
            ->whereIn('status', [HomeLayout::PUBLISHED, HomeLayout::ARCHIVED])
            ->latest('published_at')
            ->limit(10)
            ->get(['id', 'status', 'published_at'])]);
    }

    public function draft(Request $request): JsonResponse
    {
        $draft = $this->builder->draftFor($this->surface($request));

        return (new LayoutResource($draft->load(self::RELATIONS)))->response()->setStatusCode(200);
    }

    public function publish(Request $request, HomeLayout $layout): LayoutResource
    {
        return new LayoutResource($this->builder->publish($layout, $request->user()));
    }

    public function restore(Request $request, HomeLayout $layout): JsonResponse
    {
        $draft = $this->builder->restore($layout, $request->user());

        return (new LayoutResource($draft->load(self::RELATIONS)))->response()->setStatusCode(200);
    }

    public function discard(HomeLayout $layout): Response
    {
        $this->builder->discardDraft($layout);

        return response()->noContent();
    }

    public function preview(Request $request, HomeLayout $layout): JsonResponse
    {
        $group = $request->integer('group') ? StudentGroup::findOrFail($request->integer('group')) : null;

        return response()->json(['data' => $this->home->preview($layout, $group, $request->integer('course') ?: null)]);
    }

    public function storeSection(SaveSectionRequest $request, HomeLayout $layout): JsonResponse
    {
        $this->builder->assertDraft($layout);

        $section = $this->saveSection(new HomeSection([
            'home_layout_id' => $layout->id,
            'sort_order' => (int) $layout->sections()->max('sort_order') + 1,
        ]), $request);

        return (new SectionResource($section))->response()->setStatusCode(201);
    }

    public function updateSection(SaveSectionRequest $request, HomeSection $section): SectionResource
    {
        $this->builder->assertDraft($section->layoutRecord);

        return new SectionResource($this->saveSection($section, $request));
    }

    public function destroySection(HomeSection $section): Response
    {
        $this->builder->assertDraft($section->layoutRecord);
        $section->delete();

        return response()->noContent();
    }

    public function orderSections(Request $request, HomeLayout $layout): Response
    {
        $this->builder->assertDraft($layout);
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        DB::transaction(function () use ($layout, $ids) {
            foreach ($ids as $position => $id) {
                $layout->sections()->whereKey($id)->update(['sort_order' => $position]);
            }
        });

        return response()->noContent();
    }

    public function storeBanner(SaveBannerRequest $request, HomeLayout $layout): JsonResponse
    {
        $this->builder->assertDraft($layout);

        $banner = $this->saveBanner(new Banner([
            'home_layout_id' => $layout->id,
            'placement' => 'hero',
            'sort_order' => (int) $layout->banners()->max('sort_order') + 1,
        ]), $request);

        return (new BannerResource($banner))->response()->setStatusCode(201);
    }

    public function updateBanner(SaveBannerRequest $request, Banner $banner): BannerResource
    {
        $this->builder->assertDraft(HomeLayout::findOrFail($banner->home_layout_id));

        return new BannerResource($this->saveBanner($banner, $request));
    }

    public function destroyBanner(Banner $banner): Response
    {
        $this->builder->assertDraft(HomeLayout::findOrFail($banner->home_layout_id));
        $banner->delete();

        return response()->noContent();
    }

    private function surface(Request $request): string
    {
        return $request->validate(['surface' => ['required', Rule::in(HomeLayout::SURFACES)]])['surface'];
    }

    private function saveSection(HomeSection $section, SaveSectionRequest $request): HomeSection
    {
        $data = $request->validated();

        return DB::transaction(function () use ($section, $data) {
            $section->fill([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'source_type' => $data['source_type'],
                'category_id' => $data['source_type'] === 'category' ? $data['category_id'] : null,
                'source_config' => $data['source_type'] === 'teacher' ? ['teacher_id' => $data['teacher_id']] : null,
                'layout' => $data['layout'],
                'max_items' => $data['max_items'],
                'is_active' => $data['is_active'] ?? true,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
            ])->save();

            $ids = $data['source_type'] === 'manual' ? ($data['material_ids'] ?? []) : [];
            $section->materials()->sync(collect($ids)->values()->mapWithKeys(fn (int $id, int $position) => [$id => ['sort_order' => $position]]));
            $section->syncAudience($data['group_ids'] ?? []);

            return $section->load('materials', 'audienceTargets');
        });
    }

    private function saveBanner(Banner $banner, SaveBannerRequest $request): Banner
    {
        $data = $request->validated();

        return DB::transaction(function () use ($banner, $data) {
            $banner->fill(collect($data)->except('group_ids')->all() + ['is_active' => true])->save();
            $banner->syncAudience($data['group_ids'] ?? []);

            return $banner->load('image', 'material', 'audienceTargets');
        });
    }
}
