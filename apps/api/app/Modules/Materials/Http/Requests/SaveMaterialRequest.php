<?php

namespace App\Modules\Materials\Http\Requests;

use App\Modules\Materials\Enums\MaterialType;
use App\Modules\Materials\Enums\MaterialVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMaterialRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::enum(MaterialType::class)],
            'visibility' => ['required', Rule::enum(MaterialVisibility::class)],
            'teacher_id' => ['nullable', 'integer', Rule::exists('teachers', 'id')->whereNull('deleted_at')],
            'cover_upload_id' => ['nullable', 'integer', 'exists:uploads,id'],
            'file_upload_id' => ['nullable', 'integer', 'exists:uploads,id'],
            'external_url' => ['nullable', 'url:https', 'max:1000'],
            'publish_at' => ['nullable', 'date'],
            'allow_download' => ['boolean'],
            'is_featured' => ['boolean'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'primary_category_id' => ['nullable', 'integer'],
            'tags' => ['array', 'max:20'],
            'tags.*' => ['string', 'max:80'],
            'collaborator_ids' => ['array'],
            'collaborator_ids.*' => ['integer', 'exists:teachers,id'],
            'group_ids' => ['array'],
            'group_ids.*' => ['integer', 'exists:student_groups,id'],
            'student_ids' => ['array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'título',
            'description' => 'descrição',
            'type' => 'tipo',
            'visibility' => 'visibilidade',
            'external_url' => 'endereço',
            'publish_at' => 'data de publicação',
        ];
    }
}
