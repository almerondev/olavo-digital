<?php

namespace App\Modules\Home\Http\Requests;

use App\Modules\Home\Models\HomeSection;
use App\Modules\Home\Sources\SourceRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSectionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'source_type' => ['required', Rule::in(SourceRegistry::types())],
            'category_id' => ['nullable', 'required_if:source_type,category', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'teacher_id' => ['nullable', 'required_if:source_type,teacher', 'integer', 'exists:teachers,id'],
            'layout' => ['required', Rule::in(HomeSection::LAYOUTS)],
            'max_items' => ['required', 'integer', 'min:1', 'max:60'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'material_ids' => ['array'],
            'material_ids.*' => ['integer', 'exists:materials,id'],
            'group_ids' => ['array'],
            'group_ids.*' => ['integer', 'exists:student_groups,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'título', 'source_type' => 'origem', 'category_id' => 'categoria', 'teacher_id' => 'professor',
            'layout' => 'formato', 'max_items' => 'quantidade de cards', 'ends_at' => 'fim do período', 'starts_at' => 'início do período',
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required_if' => 'Escolha a categoria desta seção.',
            'teacher_id.required_if' => 'Escolha o professor desta seção.',
            'ends_at.after' => 'O fim do período precisa ser depois do início.',
        ];
    }
}
