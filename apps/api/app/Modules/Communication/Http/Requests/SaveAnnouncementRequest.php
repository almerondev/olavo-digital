<?php

namespace App\Modules\Communication\Http\Requests;

use App\Modules\Communication\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:2000'],
            'image_upload_id' => ['nullable', 'integer', 'exists:uploads,id'],
            'display_mode' => ['required', Rule::in(Announcement::MODES)],
            'priority' => ['required', 'integer', 'between:0,2'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'published_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
            'is_pinned' => ['required', 'boolean'],
            'requires_ack' => ['required', 'boolean'],
            'send_notification' => ['required', 'boolean'],
            'group_ids' => ['present', 'array'],
            'group_ids.*' => ['integer', 'exists:student_groups,id'],
        ];
    }

    public function attributes(): array
    {
        return ['title' => 'título', 'body' => 'descrição', 'published_at' => 'data de publicação', 'expires_at' => 'data de expiração'];
    }
}
