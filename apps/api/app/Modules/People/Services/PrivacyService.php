<?php

namespace App\Modules\People\Services;

use App\Modules\Access\Models\Student;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\SessionManager;
use App\Modules\Communication\Models\AnnouncementRead;
use App\Modules\Communication\Models\NotificationRecipient;
use App\Modules\Communication\Models\PushDevice;
use App\Modules\Uploads\Models\Upload;
use App\Support\Audit\ActivityLog;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Direitos do titular: acesso aos próprios dados e eliminação. */
class PrivacyService
{
    public function __construct(private SessionManager $sessions)
    {
    }

    /** @return array<string, mixed> */
    public function export(User $user): array
    {
        $student = $user->student;

        return [
            'gerado_em' => now()->toIso8601String(),
            'conta' => [
                'nome' => $user->name,
                'email' => $user->email,
                'telefone' => $user->phone,
                'status' => $user->status->value,
                'criada_em' => $user->created_at?->toIso8601String(),
                'ultimo_acesso' => $user->last_login_at?->toIso8601String(),
                'termos_versao' => $user->terms_version,
                'termos_aceitos_em' => $user->terms_accepted_at?->toIso8601String(),
                'preferencias' => $user->preferences,
            ],
            'aluno' => $student ? [
                'matricula' => $student->enrollment_code,
                'nascimento' => $student->birth_date?->toDateString(),
                'responsavel' => $student->guardian_name,
                'contato_responsavel' => $student->guardian_contact,
                'cursos' => $student->courses()->pluck('name'),
                'turmas' => $student->groups()->pluck('name'),
                'favoritos' => DB::table('favorites')->join('materials', 'materials.id', '=', 'favorites.material_id')
                    ->where('student_id', $student->id)->pluck('materials.title'),
                'historico' => DB::table('watch_history')->join('materials', 'materials.id', '=', 'watch_history.material_id')
                    ->where('student_id', $student->id)->orderByDesc('started_at')
                    ->get(['materials.title as material', 'started_at', 'seconds_watched']),
                'progresso' => DB::table('watch_progress')->join('materials', 'materials.id', '=', 'watch_progress.material_id')
                    ->where('student_id', $student->id)
                    ->get(['materials.title as material', 'position_seconds', 'duration_seconds', 'completed_at']),
            ] : null,
            'notificacoes' => NotificationRecipient::where('user_id', $user->id)->with('notification:id,title,body')->latest('id')->get()
                ->map(fn (NotificationRecipient $row) => ['titulo' => $row->notification->title, 'mensagem' => $row->notification->body, 'recebida_em' => $row->created_at, 'lida_em' => $row->read_at]),
            'avisos_confirmados' => AnnouncementRead::where('user_id', $user->id)->pluck('read_at', 'announcement_id'),
        ];
    }

    /**
     * Irreversível. Mantém só o histórico sem identificação, para as estatísticas
     * da plataforma continuarem corretas.
     */
    public function anonymize(Student $student, User $actor): void
    {
        $user = $student->user;
        $avatar = $user->avatar_upload_id ? Upload::find($user->avatar_upload_id) : null;

        DB::transaction(function () use ($student, $user) {
            $user->forceFill([
                'name' => 'Aluno removido',
                'email' => "removido-{$user->id}@anonimo.invalid",
                'phone' => null,
                'avatar_upload_id' => null,
                'preferences' => null,
                'password' => Str::random(60),
                'remember_token' => null,
                'status' => UserStatus::Blocked,
                'deletion_requested_at' => null,
            ])->save();

            $student->update(['enrollment_code' => null, 'birth_date' => null, 'guardian_name' => null, 'guardian_contact' => null, 'external_ref' => null]);
            $student->courses()->detach();
            $student->groups()->detach();

            DB::table('favorites')->where('student_id', $student->id)->delete();
            DB::table('watch_progress')->where('student_id', $student->id)->delete();
            DB::table('audience_targets')->where('student_id', $student->id)->delete();
            NotificationRecipient::where('user_id', $user->id)->delete();
            PushDevice::where('user_id', $user->id)->delete();
            ActivityLog::where('actor_id', $user->id)->update(['ip_address' => null, 'user_agent' => null]);

            $student->delete();
            $user->delete();
        });

        $this->sessions->revokeAll($user);

        if ($avatar) {
            Storage::disk($avatar->disk)->delete([$avatar->object_key, ...array_values($avatar->meta['variants'] ?? [])]);
            $avatar->delete();
        }

        Audit::record('student.anonymized', $student, actor: $actor);
    }
}
