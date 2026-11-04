<?php

namespace App\Modules\Uploads\Http\Requests;

use App\Modules\Uploads\Enums\UploadPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartUploadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'purpose' => ['required', Rule::enum(UploadPurpose::class)],
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return ['purpose' => 'finalidade', 'name' => 'nome do arquivo', 'size' => 'tamanho'];
    }
}
