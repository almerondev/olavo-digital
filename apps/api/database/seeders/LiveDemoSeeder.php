<?php

namespace Database\Seeders;

use App\Modules\Cast\Models\Teacher;
use App\Modules\Catalog\Models\Category;
use App\Modules\LiveTv\Models\LiveChannel;
use App\Modules\LiveTv\Models\LiveSchedule;
use App\Modules\LiveTv\Models\LiveStream;
use App\Modules\LiveTv\Providers\LiveProvider;
use App\Modules\Materials\Models\Material;
use Illuminate\Database\Seeder;

/** Canal, programação e gravações de exemplo, para a Olavo TV não abrir vazia. */
class LiveDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (LiveChannel::exists()) {
            return;
        }

        $provider = app(LiveProvider::class);
        $channel = LiveChannel::create(['name' => 'Estúdio principal', 'provider' => $provider->name()] + $provider->createInput('Estúdio principal'));

        $teacher = fn (string $slug) => Teacher::where('slug', $slug)->value('id');
        $category = fn (string $slug) => Category::where('slug', $slug)->value('id');

        $upcoming = [
            ['Aulão de Revolução Francesa', 'Revisão ao vivo com questões comentadas.', 'ana-beatriz-moura', 'historia', 1, 19],
            ['Plantão de Matemática', 'Tire suas dúvidas de funções e porcentagem.', 'rafael-teixeira', 'matematica', 2, 18],
            ['Oficina de Redação', 'Correção coletiva de introduções.', 'marcos-vilela', 'redacao', 3, 20],
            ['Química em 60 minutos', 'Ligações químicas e tabela periódica.', 'camila-duarte', 'quimica', 5, 19],
        ];

        foreach ($upcoming as [$title, $description, $slug, $subject, $days, $hour]) {
            $start = now()->addDays($days)->setTime($hour, 0);

            LiveSchedule::create([
                'live_channel_id' => $channel->id, 'title' => $title, 'description' => $description,
                'teacher_id' => $teacher($slug), 'category_id' => $category($subject),
                'starts_at' => $start, 'ends_at' => $start->copy()->addHours(2),
            ]);
        }

        $recording = Material::where('title', 'Olavo Pré-Vestibular: nossa história')->first();

        if ($recording) {
            $start = now()->subDays(3)->setTime(19, 0);
            $past = LiveSchedule::create([
                'live_channel_id' => $channel->id, 'title' => 'Live de abertura do ano letivo', 'teacher_id' => $teacher('ana-beatriz-moura'),
                'starts_at' => $start, 'ends_at' => $start->copy()->addHours(1), 'status' => 'ended',
            ]);

            LiveStream::create([
                'live_schedule_id' => $past->id, 'live_channel_id' => $channel->id, 'provider_session_ref' => 'demo-abertura',
                'started_at' => $start, 'ended_at' => $start->copy()->addHour(), 'material_id' => $recording->id,
            ]);
        }
    }
}
