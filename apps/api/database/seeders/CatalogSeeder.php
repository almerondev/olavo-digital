<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CategoryType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Tipos e matérias iniciais. Depois de criados, tudo é editável no painel. */
class CatalogSeeder extends Seeder
{
    private const TYPES = [
        'materia' => 'Matéria',
        'avisos' => 'Avisos',
        'revisoes' => 'Revisões',
        'eventos' => 'Eventos',
        'cursos' => 'Cursos',
        'especial' => 'Conteúdo especial',
        'personalizada' => 'Categoria personalizada',
    ];

    private const SUBJECTS = ['Matemática', 'Física', 'Química', 'Biologia', 'História', 'Geografia', 'Linguagens', 'Redação', 'Atualidades'];

    public function run(): void
    {
        foreach (array_keys(self::TYPES) as $position => $slug) {
            CategoryType::firstOrCreate(['slug' => $slug], ['name' => self::TYPES[$slug], 'is_system' => true, 'sort_order' => $position]);
        }

        $subjectType = CategoryType::where('slug', 'materia')->value('id');

        foreach (self::SUBJECTS as $position => $name) {
            Category::firstOrCreate(['slug' => Str::slug($name)], [
                'category_type_id' => $subjectType,
                'name' => $name,
                'sort_order' => $position,
            ]);
        }
    }
}
