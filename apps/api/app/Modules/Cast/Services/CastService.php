<?php

namespace App\Modules\Cast\Services;

use App\Modules\Access\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Uploads\Enums\UploadPurpose;
use App\Modules\Uploads\Models\Upload;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CastService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): Teacher
    {
        $teacher = DB::transaction(function () use ($data) {
            $user = isset($data['email']) ? $this->createAccount($data['name'], $data['email']) : null;

            $teacher = Teacher::create([
                'user_id' => $user?->id,
                'slug' => $this->uniqueSlug($data['name']),
                'display_name' => $data['name'],
                'sort_order' => (int) Teacher::max('sort_order') + 1,
            ]);

            $this->fill($teacher, $data);

            return $teacher;
        });

        // O professor define a própria senha pelo link: ninguém conhece a senha inicial.
        if ($teacher->user) {
            Password::sendResetLink(['email' => $teacher->user->email]);
        }

        Audit::record('teacher.created', $teacher, actor: $actor);

        return $teacher;
    }

    /** @param array<string, mixed> $data */
    public function update(Teacher $teacher, array $data, User $actor): Teacher
    {
        DB::transaction(function () use ($teacher, $data) {
            if (isset($data['name'])) {
                $teacher->display_name = $data['name'];
            }

            $this->fill($teacher, $data);
        });

        Audit::record('teacher.updated', $teacher, actor: $actor);

        return $teacher;
    }

    /** Campos que o próprio professor pode alterar. @param array<string, mixed> $data */
    public function updateOwnProfile(Teacher $teacher, array $data, User $actor): Teacher
    {
        DB::transaction(fn () => $this->fillProfile($teacher, $data));
        Audit::record('teacher.profile_updated', $teacher, actor: $actor);

        return $teacher;
    }

    /** @param array<string, mixed> $data */
    private function fill(Teacher $teacher, array $data): void
    {
        $teacher->fill(array_intersect_key($data, array_flip(['publish_mode', 'show_in_cast', 'status'])));
        $this->fillProfile($teacher, $data);

        if (array_key_exists('category_ids', $data)) {
            $teacher->categories()->sync($data['category_ids']);
        }
    }

    /** @param array<string, mixed> $data */
    private function fillProfile(Teacher $teacher, array $data): void
    {
        foreach (['photo_upload_id', 'banner_upload_id'] as $field) {
            $this->assertImage($data[$field] ?? null, $field);
        }

        $teacher->fill(array_intersect_key($data, array_flip(['short_bio', 'bio', 'specialties', 'photo_upload_id', 'banner_upload_id'])))->save();

        if (array_key_exists('socials', $data)) {
            $teacher->socialLinks()->delete();

            foreach (array_values(array_filter($data['socials'], fn (array $link) => ! empty($link['url']))) as $position => $link) {
                $teacher->socialLinks()->create(['network' => $link['network'], 'url' => $link['url'], 'sort_order' => $position]);
            }
        }
    }

    private function assertImage(?int $uploadId, string $field): void
    {
        if ($uploadId && Upload::whereKey($uploadId)->value('purpose') !== UploadPurpose::Image) {
            throw ValidationException::withMessages([$field => 'Envie uma imagem.']);
        }
    }

    private function createAccount(string $name, string $email): User
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => Str::password(32)]);
        $user->roles()->attach(Role::where('slug', Role::TEACHER)->value('id'));

        return $user;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'professor';
        $slug = $base;

        for ($i = 2; Teacher::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
