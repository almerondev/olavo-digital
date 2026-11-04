<?php

namespace App\Modules\Access\Http\Requests;

use App\Modules\Access\Services\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(PermissionCatalog::all()))],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nome', 'description' => 'descrição', 'permissions' => 'permissões'];
    }
}
