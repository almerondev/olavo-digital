<?php

namespace App\Modules\LiveTv\Http\Requests;

use App\Support\YouTubeUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveScheduleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'youtube_url' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail) {
                if (! YouTubeUrl::id($value)) {
                    $fail('Cole um link válido do YouTube.');
                }
            }],
            'teacher_id' => ['nullable', 'integer', Rule::exists('teachers', 'id')->whereNull('deleted_at')],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'course_id' => ['nullable', 'integer', Rule::exists('courses', 'id')->whereNull('deleted_at')],
            'cover_upload_id' => ['nullable', 'integer', 'exists:uploads,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'notify_on_start' => ['boolean'],
            'group_ids' => ['array'],
            'group_ids.*' => ['integer', 'exists:student_groups,id'],
        ];
    }

    public function attributes(): array
    {
        return ['title' => 'título', 'youtube_url' => 'link do YouTube', 'starts_at' => 'início', 'ends_at' => 'término'];
    }

    public function messages(): array
    {
        return ['ends_at.after' => 'O término precisa ser depois do início.'];
    }
}
