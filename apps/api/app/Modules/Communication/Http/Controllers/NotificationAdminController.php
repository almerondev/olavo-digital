<?php

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Communication\Models\PlatformNotification;
use App\Modules\Communication\Services\NotificationCenter;
use App\Support\Audit\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationAdminController extends Controller
{
    private const TARGET_TABLES = ['course' => 'courses', 'group' => 'student_groups', 'user' => 'users'];

    public function index(): JsonResponse
    {
        $items = PlatformNotification::with('sender:id,name')
            ->withCount(['recipients as read_count' => fn (Builder $recipients) => $recipients->whereNotNull('read_at')])
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $items->map(fn (PlatformNotification $item) => [
            'id' => $item->id,
            'type' => $item->type,
            'title' => $item->title,
            'body' => $item->body,
            'sender' => $item->sender?->name,
            'recipients_count' => $item->recipients_count,
            'read_count' => $item->read_count,
            'created_at' => $item->created_at->toIso8601String(),
        ])]);
    }

    public function store(Request $request, NotificationCenter $center): JsonResponse
    {
        $target = $request->input('target');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:500'],
            'url' => ['nullable', 'string', 'max:500', 'regex:#^(/[^/\\\\]|https://)#'],
            'target' => ['required', Rule::in(['all', 'course', 'group', 'user'])],
            'ids' => [Rule::requiredIf($target !== 'all'), 'array', 'max:100'],
            'ids.*' => ['integer', Rule::exists(self::TARGET_TABLES[$target] ?? 'users', 'id')],
        ], [], ['title' => 'título', 'body' => 'mensagem', 'url' => 'link', 'ids' => 'destinatários']);

        $notification = $center->send(
            'manual',
            $data['title'],
            $data['body'],
            $data['url'] ?? null,
            ['mode' => $data['target'], 'ids' => $data['ids'] ?? []],
            $request->user(),
        );

        Audit::record('notification.sent', null, ['notification_id' => $notification->id, 'target' => $data['target']]);

        return response()->json(['data' => ['id' => $notification->id]], 201);
    }

    /** Busca de aluno para envio individual. */
    public function recipients(Request $request): JsonResponse
    {
        $term = addcslashes((string) $request->query('q', ''), '\\%_');

        return response()->json(['data' => User::whereHas('student')
            ->where(fn (Builder $query) => $query->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email'])]);
    }
}
