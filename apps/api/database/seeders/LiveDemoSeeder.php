<?php

namespace Database\Seeders;

use App\Modules\Cast\Models\Teacher;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\LiveTv\Models\LiveSchedule;
use App\Modules\LiveTv\Models\LiveStream;
use App\Modules\Materials\Models\Material;
use Illuminate\Database\Seeder;

/** Programação e gravação de exemplo. O link do YouTube é colado pelo administrador ao iniciar. */
class LiveDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (LiveSchedule::exists()) {
            return;
        }

        $teacher = fn (string $slug) => Teacher::where('slug', $slug)->value('id');
        $category = fn (string $slug) => Category::where('slug', $slug)->value('id');
        $course = fn (string $slug) => Course::where('slug', $slug)->value('id');

        $upcoming = [
            ['Aulão de Revolução Francesa', 'Revisão ao vivo com questões comentadas.', 'ana-beatriz-moura', 'historia', 'preparatorio-enem', 1, 19],
            ['Plantão de Matemática', 'Tire suas dúvidas de funções e porcentagem.', 'rafael-teixeira', 'matematica', 'preparatorio-enem', 2, 18],
            ['Oficina de Redação', 'Correção coletiva de introduções.', 'marcos-vilela', 'redacao', 'preparatorio-enem', 3, 20],
            ['Revisão de Matemática para a ESA', 'Questões das últimas provas da ESA.', 'rafael-teixeira', 'esa-matematica', 'preparatorio-esa', 4, 19],
        ];

        foreach ($upcoming as [$title, $description, $slug, $subject, $courseSlug, $days, $hour]) {
            $start = now()->addDays($days)->setTime($hour + 3, 0);

            LiveSchedule::create([
                'title' => $title, 'description' => $description, 'teacher_id' => $teacher($slug),
                'category_id' => $category($subject), 'course_id' => $course($courseSlug),
                'starts_at' => $start, 'ends_at' => $start->copy()->addHours(2),
            ]);
        }

        $recording = Material::where('title', 'Olavo Pré-Vestibular: nossa história')->first();

        if ($recording) {
            $start = now()->subDays(3)->setTime(22, 0);
            $past = LiveSchedule::create([
                'title' => 'Live de abertura do ano letivo', 'teacher_id' => $teacher('ana-beatriz-moura'),
                'starts_at' => $start, 'ends_at' => $start->copy()->addHour(), 'status' => 'ended',
            ]);

            LiveStream::create(['live_schedule_id' => $past->id, 'started_at' => $start, 'ended_at' => $start->copy()->addHour(), 'material_id' => $recording->id]);
        }
    }
}
