<?php

namespace App\Modules\Cast\Http\Requests;

use App\Modules\Cast\Models\TeacherSocialLink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOwnProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'short_bio' => ['nullable', 'string', 'max:280'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'specialties' => ['array', 'max:12'],
            'specialties.*' => ['string', 'max:80'],
            'photo_upload_id' => ['nullable', 'integer', 'exists:uploads,id'],
            'banner_upload_id' => ['nullable', 'integer', 'exists:uploads,id'],
            'socials' => ['array'],
            'socials.*.network' => ['required', Rule::in(TeacherSocialLink::NETWORKS)],
            'socials.*.url' => ['nullable', 'url:https', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['short_bio' => 'descrição curta', 'bio' => 'biografia', 'socials.*.url' => 'endereço'];
    }
}
