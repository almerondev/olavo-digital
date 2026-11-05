<?php

namespace App\Modules\Materials\Services;

use App\Modules\Materials\Enums\MaterialType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/** Pesquisa por nome, descrição, tags, matéria, professor e tipo de conteúdo. */
class MaterialSearch
{
    private const VIDEO = [MaterialType::Video, MaterialType::ExternalVideo];

    private const TYPE_WORDS = [
        'video' => self::VIDEO,
        'videos' => self::VIDEO,
        'videoaula' => self::VIDEO,
        'videoaulas' => self::VIDEO,
        'aula' => self::VIDEO,
        'aulas' => self::VIDEO,
        'pdf' => [MaterialType::Pdf],
        'pdfs' => [MaterialType::Pdf],
        'audio' => [MaterialType::Audio],
        'audios' => [MaterialType::Audio],
        'imagem' => [MaterialType::Image],
        'imagens' => [MaterialType::Image],
        'link' => [MaterialType::Link],
        'links' => [MaterialType::Link],
        'documento' => [MaterialType::Document],
        'documentos' => [MaterialType::Document],
        'apresentacao' => [MaterialType::Presentation],
        'apresentacoes' => [MaterialType::Presentation],
        'slides' => [MaterialType::Presentation],
        'planilha' => [MaterialType::Spreadsheet],
        'planilhas' => [MaterialType::Spreadsheet],
    ];

    public static function pattern(string $term): string
    {
        return '%'.addcslashes(trim($term), '\\%_').'%';
    }

    public static function apply(Builder $query, string $term): Builder
    {
        $like = self::pattern($term);
        $types = array_map(fn (MaterialType $type) => $type->value, self::TYPE_WORDS[Str::lower(Str::ascii(trim($term)))] ?? []);

        return $query->where(fn (Builder $match) => $match
            ->where('materials.title', 'like', $like)
            ->orWhere('materials.description', 'like', $like)
            ->orWhereHas('tags', fn (Builder $tags) => $tags->where('name', 'like', $like))
            ->orWhereHas('categories', fn (Builder $categories) => $categories->where('categories.name', 'like', $like))
            ->orWhereHas('teacher', fn (Builder $teacher) => $teacher->where('display_name', 'like', $like))
            ->when($types !== [], fn (Builder $match) => $match->orWhereIn('materials.type', $types)));
    }
}
