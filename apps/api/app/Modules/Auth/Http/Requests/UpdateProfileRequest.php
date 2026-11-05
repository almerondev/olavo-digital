<?php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'preferences' => ['sometimes', 'array'],
            'avatar_upload_id' => ['sometimes', 'nullable', 'integer', Rule::exists('uploads', 'id')->where('uploaded_by', $this->user()->id)->where('purpose', 'avatar')],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nome', 'phone' => 'telefone'];
    }
}
