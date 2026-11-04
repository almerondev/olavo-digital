<?php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'preferences' => ['sometimes', 'array'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nome', 'phone' => 'telefone'];
    }
}
