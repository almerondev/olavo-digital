<?php

namespace Database\Seeders;

use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Communication\Models\Announcement;
use Illuminate\Database\Seeder;

class CommunicationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@olavo.test')->firstOrFail();
        $esa = Course::where('slug', 'preparatorio-esa')->firstOrFail();

        $base = ['published_at' => now()->subHour(), 'created_by' => $admin->id, 'notified_at' => now()];

        Announcement::create([...$base, 'title' => 'Boas-vindas ao Olavo Digital', 'body' => 'Seus conteúdos, transmissões e materiais agora ficam reunidos aqui.', 'display_mode' => 'banner', 'priority' => 1, 'is_pinned' => true]);
        Announcement::create([...$base, 'title' => 'Inscrições para o simulado', 'body' => 'O simulado geral acontece no próximo sábado. Confirme sua leitura para reservar sua vaga.', 'display_mode' => 'card', 'priority' => 2, 'requires_ack' => true]);
        Announcement::create([...$base, 'title' => 'Calendário ESA atualizado', 'body' => 'As datas de revisão da ESA foram atualizadas para este mês.', 'display_mode' => 'section', 'course_id' => $esa->id]);
    }
}
