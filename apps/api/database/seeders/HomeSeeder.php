<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Home\Models\HomeLayout;
use App\Modules\Home\Services\HomeService;
use Illuminate\Database\Seeder;

/** Home inicial. As seções são dados: o construtor da fase 4 edita este mesmo layout. */
class HomeSeeder extends Seeder
{
    public function run(): void
    {
        $this->surface(HomeLayout::SURFACE_HOME, 'Início');
        $this->surface(HomeLayout::SURFACE_MATERIALS, 'Materiais');

        app(HomeService::class)->flush();
    }

    private function surface(string $surface, string $name): void
    {
        if (HomeLayout::where('surface', $surface)->exists()) {
            return;
        }

        $layout = HomeLayout::create(['name' => $name, 'surface' => $surface, 'status' => HomeLayout::PUBLISHED, 'published_at' => now()]);

        $layout->sections()->createMany([
            ['title' => 'Continuar assistindo', 'source_type' => 'continue_watching', 'sort_order' => -3],
            ['title' => 'Destaques', 'source_type' => 'featured', 'sort_order' => -2],
            ['title' => 'Recomendados para você', 'source_type' => 'recommended', 'sort_order' => -1],
            ['title' => 'Novidades', 'source_type' => 'recent', 'sort_order' => 0],
        ]);

        foreach (Category::whereNull('parent_id')->orderBy('sort_order')->get() as $position => $category) {
            $layout->sections()->create([
                'title' => $category->name,
                'source_type' => 'category',
                'category_id' => $category->id,
                'sort_order' => $position + 2,
            ]);
        }
    }
}
