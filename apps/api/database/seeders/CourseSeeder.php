<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CategoryType;
use App\Modules\Catalog\Models\Course;
use Illuminate\Database\Seeder;

/** Preparatórios e matérias iniciais. Tudo pode ser ajustado no painel. */
class CourseSeeder extends Seeder
{
    private const OTHER_COURSES = [
        'preparatorio-esa' => ['Preparatório ESA', 'esa', ['Matemática', 'Português', 'Inglês', 'História do Brasil', 'Geografia do Brasil']],
        'preparatorio-iffar' => ['Preparatório IFFar', 'iffar', ['Português', 'Matemática']],
    ];

    public function run(): void
    {
        $enem = Course::firstOrCreate(['slug' => 'preparatorio-enem'], ['name' => 'Preparatório ENEM', 'sort_order' => 0]);
        $subjectType = CategoryType::where('slug', 'materia')->value('id');

        // As matérias criadas por CatalogSeeder formam o currículo do ENEM.
        Category::whereNull('course_id')->where('category_type_id', $subjectType)->update(['course_id' => $enem->id]);

        $position = 1;

        foreach (self::OTHER_COURSES as $slug => [$name, $prefix, $subjects]) {
            $course = Course::firstOrCreate(['slug' => $slug], ['name' => $name, 'sort_order' => $position++]);

            foreach ($subjects as $order => $subject) {
                Category::firstOrCreate(['slug' => $prefix.'-'.str($subject)->slug()], [
                    'category_type_id' => $subjectType,
                    'course_id' => $course->id,
                    'name' => $subject,
                    'sort_order' => $order,
                ]);
            }
        }
    }
}
