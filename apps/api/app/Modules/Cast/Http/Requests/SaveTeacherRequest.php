<?php

namespace App\Modules\Cast\Http\Requests;

use App\Modules\Cast\Models\TeacherSocialLink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTeacherRequest extends FormRequest
{
    public function rules(): array
    {
        $teacher = $this->route('teacher');

        return [
            'name' => [$teacher ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => [$teacher ? 'prohibited' : 'nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'short_bio' => ['nullable', 'string', 'max:280'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'specialties' => ['array', 'max:12'],
            'specialties.*' => ['string', 'max:80'],
            'photo_upload_id' => ['nullable', 'integer', 'exists:uploads,id'],
            'banner_upload_id' => ['nullable', 'integer', 'exists:uploads,id'],
            'socials' => ['array'],
            'socials.*.network' => ['required', Rule::in(TeacherSocialLink::NETWORKS)],
            'socials.*.url' => ['nullable', 'url:https', 'max:500'],
            'publish_mode' => ['sometimes', Rule::in(['inherit', 'immediate', 'approval'])],
            'show_in_cast' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nome', 'email' => 'e-mail', 'short_bio' => 'descrição curta', 'bio' => 'biografia', 'socials.*.url' => 'endereço'];
    }
}
