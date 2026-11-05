<?php

namespace App\Modules\People\Services;

use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\Student;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\SessionManager;
use App\Support\Audit\Audit;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class StudentService
{
    private const USER_FIELDS = ['name', 'email', 'phone'];
    private const STUDENT_FIELDS = ['enrollment_code', 'birth_date', 'guardian_name', 'guardian_contact'];

    public function __construct(private SessionManager $sessions)
    {
    }

    /** A senha nasce aleatória: o aluno define a dele pelo link enviado ao e-mail. */
    public function create(array $data, User $actor): Student
    {
        $student = DB::transaction(function () use ($data) {
            $user = User::create([...Arr::only($data, self::USER_FIELDS), 'password' => Str::random(40), 'status' => UserStatus::Active]);
            $user->roles()->attach(Role::where('slug', Role::STUDENT)->value('id'));

            $student = Student::create(['user_id' => $user->id, ...Arr::only($data, self::STUDENT_FIELDS)]);
            $this->link($student, $data);

            return $student;
        });

        Password::sendResetLink(['email' => $student->user->email]);
        Audit::record('student.created', $student, actor: $actor);

        return $student;
    }

    public function update(Student $student, array $data, User $actor): void
    {
        DB::transaction(function () use ($student, $data) {
            $student->user->update(Arr::only($data, self::USER_FIELDS));
            $student->update(Arr::only($data, self::STUDENT_FIELDS));
            $this->link($student, $data);
        });

        Audit::record('student.updated', $student, actor: $actor);
    }

    public function setBlocked(Student $student, bool $blocked, User $actor): void
    {
        $student->user->update(['status' => $blocked ? UserStatus::Blocked : UserStatus::Active]);

        if ($blocked) {
            $this->sessions->revokeAll($student->user);
        }

        Audit::record($blocked ? 'student.blocked' : 'student.unblocked', $student, actor: $actor);
    }

    public function sendPasswordReset(Student $student, User $actor): void
    {
        Password::sendResetLink(['email' => $student->user->email]);
        Audit::record('student.password_reset_sent', $student, actor: $actor);
    }

    private function link(Student $student, array $data): void
    {
        $student->courses()->sync($data['course_ids']);
        $student->groups()->sync($data['group_ids']);
    }
}
