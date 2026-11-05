<?php

namespace App\Modules\Home\Services;

use App\Modules\Access\Models\AudienceTarget;
use App\Modules\Access\Models\StudentGroup;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Communication\Services\AnnouncementBoard;
use App\Modules\Home\Models\Banner;
use App\Modules\Home\Models\HomeLayout;
use App\Modules\Home\Models\HomeSection;
use App\Modules\Home\Sources\SourceRegistry;
use App\Modules\LiveTv\Services\LiveDirectory;
use App\Modules\Materials\Http\Resources\MaterialCardResource;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Services\MaterialAccessService;
use App\Modules\Uploads\Models\Upload;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class HomeService
{
    private const PAGE_SIZE = 12;

    /** Seções que chegam prontas; as demais carregam ao entrar na tela. */
    private const EAGER_SECTIONS = 3;

    private const CARD_RELATIONS = ['teacher', 'cover', 'banner', 'categories'];

    public function __construct(
        private SourceRegistry $sources,
        private MaterialAccessService $access,
        private HeroPreview $previews,
        private LiveDirectory $live,
        private AnnouncementBoard $announcements,
    ) {
    }

    /** @return array{hero: list<array<string, mixed>>, sections: list<array<string, mixed>>} */
    public function forUser(User $user, string $surface = HomeLayout::SURFACE_HOME): array
    {
        $layout = $this->published($surface);

        if (! $layout) {
            return ['hero' => [], 'announcements' => [], 'sections' => []];
        }

        return $this->build($layout, $user, self::EAGER_SECTIONS);
    }

    /**
     * Mostra um layout como um aluno da turma veria, inclusive rascunhos. Seções pessoais ficam
     * vazias porque o aluno simulado não tem histórico.
     *
     * @return array{hero: list<array<string, mixed>>, sections: list<array<string, mixed>>}
     */
    public function preview(HomeLayout $layout, ?StudentGroup $group, ?int $courseId = null): array
    {
        $layout->load('sections.audienceTargets', 'banners.audienceTargets', 'banners.image', 'banners.video');

        return $this->build($layout, User::simulatedStudent($group ? [$group->id] : [], $courseId ? [$courseId] : []), PHP_INT_MAX);
    }

    /** @return array{hero: list<array<string, mixed>>, sections: list<array<string, mixed>>} */
    private function build(HomeLayout $layout, User $user, int $eagerSections): array
    {
        [$studentId, $groupIds] = $this->audienceOf($user);

        // Seção de matéria de outro curso nem é consultada.
        $categories = Category::visibleTo($user)->pluck('id');

        $sections = $layout->sections
            ->filter(fn (HomeSection $section) => $section->isOnAir()
                && $section->reaches($studentId, $groupIds)
                && $this->sources->supports($section->source_type)
                && ($section->source_type !== 'category' || $categories->contains($section->category_id)))
            ->values();

        return [
            'hero' => $this->heroes($layout, $user, $studentId, $groupIds),
            'announcements' => $layout->surface === HomeLayout::SURFACE_HOME ? $this->announcements->forHome($user) : [],
            'sections' => $sections
                ->map(fn (HomeSection $section, int $position) => $this->presentSection($section, $user, $layout->surface, $position < $eagerSections))
                ->filter(fn (array $section) => ! isset($section['items']) || $section['items'] !== [])
                ->values()
                ->all(),
        ];
    }

    /** @return array{items: array<int, mixed>, has_more: bool}|null */
    public function sectionPage(HomeSection $section, User $user, int $page): ?array
    {
        $surface = $section->layoutRecord->surface;
        [$studentId, $groupIds] = $this->audienceOf($user);
        $loaded = $this->published($surface)?->sections->firstWhere('id', $section->id);

        if (! $loaded?->isOnAir() || ! $loaded->reaches($studentId, $groupIds)) {
            return null;
        }

        return $this->page($loaded, $user, $surface, $page);
    }

    public function flush(): void
    {
        foreach (HomeLayout::SURFACES as $surface) {
            Cache::forget($this->cacheKey($surface));
        }
    }

    private function cacheKey(string $surface): string
    {
        return "home:published:{$surface}";
    }

    private function published(string $surface): ?HomeLayout
    {
        $snapshot = Cache::rememberForever($this->cacheKey($surface), fn () => $this->snapshot($surface));

        return $snapshot ? $this->restore($snapshot) : null;
    }

    /**
     * O cache guarda só dados puros: o Laravel 13 recusa desserializar objetos por segurança.
     *
     * @return array<string, mixed>|null
     */
    private function snapshot(string $surface): ?array
    {
        $layout = HomeLayout::query()
            ->where('surface', $surface)
            ->where('status', HomeLayout::PUBLISHED)
            ->with('sections.audienceTargets', 'banners.audienceTargets', 'banners.image', 'banners.video')
            ->latest('published_at')
            ->first();

        if (! $layout) {
            return null;
        }

        $targets = fn (Collection $items) => $items->map(fn (AudienceTarget $target) => $target->getAttributes())->all();

        return [
            'layout' => $layout->getAttributes(),
            'sections' => $layout->sections->map(fn (HomeSection $section) => [
                'attributes' => $section->getAttributes(),
                'targets' => $targets($section->audienceTargets),
            ])->all(),
            'banners' => $layout->banners->map(fn (Banner $banner) => [
                'attributes' => $banner->getAttributes(),
                'targets' => $targets($banner->audienceTargets),
                'image' => $banner->image?->getAttributes(),
                'video' => $banner->video?->getAttributes(),
            ])->all(),
        ];
    }

    /** @param array<string, mixed> $snapshot */
    private function restore(array $snapshot): HomeLayout
    {
        $rebuild = function (string $class, array $row) {
            $model = (new $class)->newFromBuilder($row['attributes']);

            return $model->setRelation('audienceTargets', AudienceTarget::hydrate($row['targets']));
        };

        $banners = collect($snapshot['banners'])->map(function (array $row) use ($rebuild) {
            $image = $row['image'] ? (new Upload)->newFromBuilder($row['image']) : null;

            $video = $row['video'] ? (new Upload)->newFromBuilder($row['video']) : null;

            return $rebuild(Banner::class, $row)->setRelation('image', $image)->setRelation('video', $video);
        });

        return (new HomeLayout)->newFromBuilder($snapshot['layout'])
            ->setRelation('sections', collect($snapshot['sections'])->map(fn (array $row) => $rebuild(HomeSection::class, $row)))
            ->setRelation('banners', $banners);
    }

    /** @return array{0: int|null, 1: Collection<int, int>} */
    private function audienceOf(User $user): array
    {
        return [$user->student?->id, $user->audienceGroupIds()];
    }

    /** @return array{items: array<int, mixed>, has_more: bool} */
    private function page(HomeSection $section, User $user, string $surface, int $page): array
    {
        $query = $this->sources->for($section->source_type)->query($section, $user)->forSurface($surface);
        $offset = ($page - 1) * self::PAGE_SIZE;
        $limit = min(self::PAGE_SIZE, max(0, $section->max_items - $offset));

        $items = $limit === 0 ? collect() : (clone $query)
            ->with(self::CARD_RELATIONS)
            ->skip($offset)
            ->take($limit)
            ->get();

        $reached = $offset + $items->count();

        return [
            'items' => MaterialCardResource::collection($items)->resolve(),
            'has_more' => $reached < $section->max_items && (clone $query)->count() > $reached,
        ];
    }

    /** @return array<string, mixed> */
    private function presentSection(HomeSection $section, User $user, string $surface, bool $eager): array
    {
        $base = [
            'id' => $section->id,
            'title' => $section->title,
            'description' => $section->description,
            'layout' => $section->layout,
        ];

        return $eager ? $base + $this->page($section, $user, $surface, 1) : $base;
    }

    /**
     * Banners configurados têm prioridade. Sem nenhum no ar, o hero usa o destaque mais recente.
     *
     * @param  Collection<int, int>  $groupIds
     * @return list<array<string, mixed>>
     */
    private function heroes(HomeLayout $layout, User $user, ?int $studentId, Collection $groupIds): array
    {
        // Transmissão no ar passa na frente de qualquer destaque da página Início.
        $stream = $layout->surface === HomeLayout::SURFACE_HOME ? $this->live->currentFor($user) : null;

        if ($stream) {
            return [[
                'id' => 'live-'.$stream->id,
                'title' => $stream->schedule->title,
                'description' => $stream->schedule->description,
                'cta_label' => 'Assistir ao vivo',
                'image_urls' => (object) ($stream->schedule->cover?->imageUrls() ?: $stream->schedule->teacher?->banner?->imageUrls() ?: []),
                'material_slug' => null,
                'material_type' => null,
                'teacher' => $stream->schedule->teacher?->display_name,
                'category' => $stream->schedule->category?->name,
                'external_url' => null,
                'preview' => null,
                'is_live' => true,
            ]];
        }

        $banners = $layout->banners->filter(fn (Banner $banner) => $banner->placement === 'hero'
            && $banner->isOnAir()
            && $banner->reaches($studentId, $groupIds));

        $materials = $this->heroMaterials()
            ->whereIn('id', $banners->pluck('material_id')->filter())
            ->get()
            ->keyBy('id');

        $heroes = $banners
            ->map(function (Banner $banner) use ($materials, $user) {
                $material = $materials->get($banner->material_id);

                // Banner de material indisponível para este aluno não aparece.
                if ($banner->material_id && (! $material || ! $this->access->canView($user, $material))) {
                    return null;
                }

                return $this->presentHero($material, $banner);
            })
            ->filter()
            ->values()
            ->all();

        if ($heroes !== []) {
            return $heroes;
        }

        $featured = $this->heroMaterials()
            ->visibleTo($user)
            ->forSurface($layout->surface)
            ->where('is_featured', true)
            ->orderByDesc('published_at')
            ->first();

        return $featured ? [$this->presentHero($featured)] : [];
    }

    private function heroMaterials()
    {
        return Material::query()->with([...self::CARD_RELATIONS, 'primaryFile.upload']);
    }

    /** @return array<string, mixed> */
    private function presentHero(?Material $material, ?Banner $banner = null): array
    {
        return [
            'id' => $banner?->id ?? $material->id,
            'title' => $banner?->title ?? $material?->title,
            'description' => $banner?->description ?? $material?->description,
            'cta_label' => $banner?->cta_label,
            'image_urls' => (object) ($banner?->image?->imageUrls() ?: $material?->banner?->imageUrls() ?: $material?->cover?->imageUrls() ?: []),
            'material_slug' => $material?->slug,
            'material_type' => $material?->type->value,
            'teacher' => $material?->teacher?->display_name,
            'category' => $material?->categories->firstWhere('pivot.is_primary', true)?->name,
            'external_url' => $banner?->external_url,
            'preview' => $banner ? $this->previews->forBanner($banner, $material) : $this->previews->for($material),
            'is_live' => false,
        ];
    }
}
