<?php

namespace App\Modules\Materials\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Materials\Enums\MaterialStatus;
use App\Modules\Materials\Enums\MaterialType;
use App\Modules\Materials\Enums\MaterialVisibility;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Models\MaterialFile;
use App\Modules\Materials\Models\Tag;
use App\Modules\Materials\Models\VideoAsset;
use App\Modules\Uploads\Enums\UploadPurpose;
use App\Modules\Uploads\Models\Upload;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MaterialService
{
    /** @param array<string, mixed> $data */
    public function save(?Material $material, array $data, User $actor): Material
    {
        $saved = DB::transaction(function () use ($material, $data, $actor) {
            $type = MaterialType::from($data['type']);
            $teacherId = $this->resolveTeacher($data, $actor);
            $categoryIds = $data['category_ids'] ?? [];

            $this->assertCategoriesAllowed($categoryIds, $actor);
            $this->assertCover($data['cover_upload_id'] ?? null);

            $attributes = [
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => $type,
                'visibility' => $data['visibility'] ?? MaterialVisibility::All->value,
                'teacher_id' => $teacherId,
                'cover_upload_id' => $data['cover_upload_id'] ?? null,
                'external_url' => $type->requiresUrl() ? ($data['external_url'] ?? null) : null,
                'publish_at' => $data['publish_at'] ?? null,
                'allow_download' => $data['allow_download'] ?? false,
                'is_featured' => $actor->can('materials.manage_all') ? ($data['is_featured'] ?? false) : ($material?->is_featured ?? false),
                'video_asset_id' => $this->resolveExternalVideo($type, $data['external_url'] ?? null),
            ];

            if ($material) {
                $material->update($attributes);
            } else {
                $material = Material::create($attributes + [
                    'slug' => $this->uniqueSlug($data['title']),
                    'status' => MaterialStatus::Draft,
                    'created_by' => $actor->id,
                ]);
            }

            $this->syncCategories($material, $categoryIds, $data['primary_category_id'] ?? null);
            $this->syncTags($material, $data['tags'] ?? []);
            $material->collaborators()->sync($data['collaborator_ids'] ?? []);
            $this->syncPrimaryFile($material, $type, $data['file_upload_id'] ?? null);

            $restricted = $material->visibility === MaterialVisibility::Restricted;
            $material->syncAudience($restricted ? ($data['group_ids'] ?? []) : [], $restricted ? ($data['student_ids'] ?? []) : []);

            return $material;
        });

        Audit::record($saved->wasRecentlyCreated ? 'material.created' : 'material.updated', $saved, actor: $actor);

        return $saved;
    }

    public function duplicate(Material $source, User $actor): Material
    {
        $copy = DB::transaction(function () use ($source, $actor) {
            $copy = $source->replicate(['slug', 'publish_at', 'published_at', 'archived_at', 'reviewed_by', 'reviewed_at', 'review_note']);
            $copy->fill([
                'title' => Str::limit($source->title, 240, '').' (cópia)',
                'slug' => $this->uniqueSlug($source->title.' copia'),
                'status' => MaterialStatus::Draft,
                'created_by' => $actor->id,
            ])->save();

            foreach ($source->files as $file) {
                $copy->files()->create($file->only(['upload_id', 'role', 'locale', 'sort_order']));
            }

            $this->syncCategories(
                $copy,
                $source->categories->modelKeys(),
                $source->categories->firstWhere('pivot.is_primary', true)?->id,
            );
            $copy->tags()->sync($source->tags->modelKeys());
            $copy->collaborators()->sync($source->collaborators->modelKeys());
            $copy->syncAudience(
                $source->audienceTargets->pluck('student_group_id')->filter()->values()->all(),
                $source->audienceTargets->pluck('student_id')->filter()->values()->all(),
            );

            return $copy;
        });

        Audit::record('material.duplicated', $copy, ['source_id' => $source->id], $actor);

        return $copy;
    }

    public function delete(Material $material, User $actor): void
    {
        $material->delete();
        Audit::record('material.deleted', $material, actor: $actor);
    }

    /** @param array<string, mixed> $data */
    private function resolveTeacher(array $data, User $actor): ?int
    {
        return $actor->can('materials.manage_all') ? ($data['teacher_id'] ?? null) : $actor->teacher?->id;
    }

    /** @param list<int> $categoryIds */
    private function assertCategoriesAllowed(array $categoryIds, User $actor): void
    {
        if ($actor->can('materials.manage_all')) {
            return;
        }

        $allowed = DB::table('teacher_categories')
            ->where('teacher_id', $actor->teacher?->id)
            ->where('can_publish', true)
            ->pluck('category_id')
            ->all();

        if (array_diff($categoryIds, $allowed)) {
            throw ValidationException::withMessages(['category_ids' => 'Você só pode publicar nas matérias autorizadas.']);
        }
    }

    private function assertCover(?int $uploadId): void
    {
        if ($uploadId && Upload::whereKey($uploadId)->value('purpose') !== UploadPurpose::Image) {
            throw ValidationException::withMessages(['cover_upload_id' => 'A capa precisa ser uma imagem.']);
        }
    }

    private function resolveExternalVideo(MaterialType $type, ?string $url): ?int
    {
        if ($type !== MaterialType::ExternalVideo || ! $url) {
            return null;
        }

        return VideoAsset::firstOrCreate(['provider' => $this->videoProvider($url), 'provider_ref' => $url])->id;
    }

    private function videoProvider(string $url): string
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        return match (true) {
            Str::endsWith($host, ['youtube.com', 'youtu.be']) => 'youtube',
            Str::endsWith($host, 'vimeo.com') => 'vimeo',
            default => 'external',
        };
    }

    /** @param list<int> $categoryIds */
    private function syncCategories(Material $material, array $categoryIds, ?int $primaryId): void
    {
        $primaryId = in_array($primaryId, $categoryIds, true) ? $primaryId : ($categoryIds[0] ?? null);

        // Remove antes de inserir: trocar a matéria principal em update violaria o índice único.
        $material->categories()->detach();

        foreach (array_unique($categoryIds) as $categoryId) {
            $material->categories()->attach($categoryId, ['is_primary' => $categoryId === $primaryId]);
        }
    }

    /** @param list<string> $names */
    private function syncTags(Material $material, array $names): void
    {
        $ids = collect($names)
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->map(fn (string $name) => Tag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id);

        $material->tags()->sync($ids);
    }

    private function syncPrimaryFile(Material $material, MaterialType $type, ?int $uploadId): void
    {
        if ($type->requiresUrl() || ! $uploadId) {
            $material->files()->where('role', MaterialFile::ROLE_PRIMARY)->delete();

            return;
        }

        $upload = Upload::findOrFail($uploadId);

        if ($type->fileClass() && $upload->file_class !== $type->fileClass()) {
            throw ValidationException::withMessages(['file_upload_id' => 'O arquivo não corresponde ao tipo do material.']);
        }

        $material->files()->where('role', MaterialFile::ROLE_PRIMARY)->delete();
        $material->files()->create(['upload_id' => $upload->id, 'role' => MaterialFile::ROLE_PRIMARY]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 180, '') ?: 'material';
        $slug = $base;

        for ($i = 2; Material::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
