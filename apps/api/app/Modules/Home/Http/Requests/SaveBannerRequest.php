<?php

namespace App\Modules\Home\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBannerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'required_without:material_id', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'cta_label' => ['nullable', 'string', 'max:60'],
            'image_upload_id' => ['nullable', 'integer', 'exists:uploads,id'],
            'media_type' => ['sometimes', Rule::in(['image', 'preview', 'video'])],
            'video_upload_id' => ['nullable', 'required_if:media_type,video', 'integer', 'exists:uploads,id'],
            'material_id' => ['nullable', 'required_if:media_type,preview', 'integer', 'exists:materials,id'],
            'external_url' => ['nullable', 'url:https', 'max:1000'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'group_ids' => ['array'],
            'group_ids.*' => ['integer', 'exists:student_groups,id'],
        ];
    }

    public function attributes(): array
    {
        return ['title' => 'título', 'external_url' => 'endereço', 'ends_at' => 'fim do período', 'starts_at' => 'início do período'];
    }

    public function messages(): array
    {
        return [
            'title.required_without' => 'Informe um título ou escolha um conteúdo.',
            'video_upload_id.required_if' => 'Envie o vídeo do destaque.',
            'material_id.required_if' => 'Escolha o vídeo que vai ter a prévia.',
            'ends_at.after' => 'O fim do período precisa ser depois do início.',
        ];
    }
}
