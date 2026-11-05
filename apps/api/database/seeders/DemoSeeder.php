<?php

namespace Database\Seeders;

use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\Student;
use App\Modules\Access\Models\StudentGroup;
use App\Modules\Auth\Models\User;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Catalog\Models\Course;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Dados de demonstração. Só roda fora de produção e exige SEED_DEMO_PASSWORD. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEED_DEMO_PASSWORD');

        if (app()->isProduction() || ! $password) {
            throw new RuntimeException('DemoSeeder exige ambiente local e SEED_DEMO_PASSWORD.');
        }

        $roles = Role::pluck('id', 'slug');

        $admin = $this->user('Administração Olavo', 'admin@olavo.test', $password);
        $admin->roles()->syncWithoutDetaching([$roles[Role::ADMIN]]);

        $teacherUser = $this->user('Ana Beatriz Moura', 'professora@olavo.test', $password);
        $teacherUser->roles()->syncWithoutDetaching([$roles[Role::TEACHER]]);
        Teacher::firstOrCreate(['user_id' => $teacherUser->id], [
            'slug' => 'ana-beatriz-moura',
            'display_name' => 'Ana Beatriz Moura',
        ]);

        $studentUser = $this->user('Lucas Andrade', 'aluno@olavo.test', $password);
        $studentUser->roles()->syncWithoutDetaching([$roles[Role::STUDENT]]);
        $student = Student::firstOrCreate(['user_id' => $studentUser->id], ['enrollment_code' => 'OLV-0001']);

        $group = StudentGroup::firstOrCreate(['slug' => 'extensivo-2026'], ['name' => 'Extensivo 2026', 'year' => 2026]);
        $group->students()->syncWithoutDetaching([$student->id]);
        $student->courses()->syncWithoutDetaching([Course::where('slug', 'preparatorio-enem')->value('id')]);

        $esaUser = $this->user('Bruna Carvalho', 'aluno.esa@olavo.test', $password);
        $esaUser->roles()->syncWithoutDetaching([$roles[Role::STUDENT]]);
        $esa = Student::firstOrCreate(['user_id' => $esaUser->id], ['enrollment_code' => 'OLV-0002']);
        $esa->courses()->syncWithoutDetaching([Course::where('slug', 'preparatorio-esa')->value('id')]);

        $this->call([DemoContentSeeder::class, LiveDemoSeeder::class, CommunicationDemoSeeder::class]);
    }

    private function user(string $name, string $email, string $password): User
    {
        return User::firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => $password,
            'email_verified_at' => now(),
        ]);
    }
}
