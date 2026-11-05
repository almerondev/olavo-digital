<?php

namespace App\Modules\People\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $student = $this->route('student');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($student?->user_id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'enrollment_code' => ['nullable', 'string', 'max:40', Rule::unique('students', 'enrollment_code')->ignore($student?->id)],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_contact' => ['nullable', 'string', 'max:255'],
            'course_ids' => ['present', 'array'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
            'group_ids' => ['present', 'array'],
            'group_ids.*' => ['integer', 'exists:student_groups,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nome', 'email' => 'e-mail', 'phone' => 'telefone', 'enrollment_code' => 'matrícula',
            'birth_date' => 'data de nascimento', 'guardian_name' => 'responsável', 'guardian_contact' => 'contato do responsável',
        ];
    }
}
