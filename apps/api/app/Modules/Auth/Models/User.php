<?php

namespace App\Modules\Auth\Models;

use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\Student;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\Uploads\Models\Upload;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'avatar_upload_id', 'password', 'status', 'preferences', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /** @var Collection<int, int>|null */
    private ?Collection $simulatedGroups = null;

    /** @var Collection<int, int>|null */
    private ?Collection $simulatedCourses = null;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
            'status' => UserStatus::class,
        ];
    }

    public function avatar(): BelongsTo
    {
        return $this->belongsTo(Upload::class, 'avatar_upload_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')->withPivot('effect');
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    /** Aluno fictício da pré-visualização: nunca é salvo, só responde às regras de público. */
    public static function simulatedStudent(array $groupIds, array $courseIds = []): self
    {
        $user = (new self)->forceFill(['id' => 0]);
        $user->simulatedGroups = collect($groupIds);
        $user->simulatedCourses = collect($courseIds);

        return $user->setRelation('student', (new Student)->forceFill(['id' => 0]))->setRelation('teacher', null);
    }

    /** @return Collection<int, int> */
    public function audienceGroupIds(): Collection
    {
        return $this->simulatedGroups ?? $this->student?->groups()->pluck('student_groups.id') ?? collect();
    }

    /** @return Collection<int, int> */
    public function audienceCourseIds(): Collection
    {
        return $this->simulatedCourses ?? $this->student?->courses()->pluck('courses.id') ?? collect();
    }

    public function termsPending(): bool
    {
        $version = app(SettingsService::class)->get('legal.version');

        return $version !== null && $version !== $this->terms_version;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
