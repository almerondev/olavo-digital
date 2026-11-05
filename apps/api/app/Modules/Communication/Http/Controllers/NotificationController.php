<?php

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Models\NotificationRecipient;
use App\Modules\Communication\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Central do aluno: toda leitura parte da linha de destinatário dele. */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = NotificationRecipient::where('user_id', $request->user()->id)
            ->with('notification.image')
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (NotificationRecipient $row) => [
                'id' => $row->id,
                'type' => $row->notification->type,
                'title' => $row->notification->title,
                'body' => $row->notification->body,
                'url' => $row->notification->url,
                'image_urls' => (object) ($row->notification->image?->imageUrls() ?? []),
                'read_at' => $row->read_at?->toIso8601String(),
                'created_at' => $row->created_at->toIso8601String(),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'count' => NotificationRecipient::where('user_id', $request->user()->id)->whereNull('read_at')->count(),
        ]]);
    }

    public function read(Request $request, int $notification): Response
    {
        NotificationRecipient::where('user_id', $request->user()->id)->findOrFail($notification)->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function readAll(Request $request): Response
    {
        NotificationRecipient::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function subscribePush(Request $request): Response
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:1000'],
            'public_key' => ['required', 'string', 'max:255'],
            'auth_token' => ['required', 'string', 'max:255'],
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [...$data, 'user_id' => $request->user()->id, 'user_agent' => substr((string) $request->userAgent(), 0, 255)],
        );

        return response()->noContent();
    }

    public function unsubscribePush(Request $request): Response
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:1000']]);

        PushSubscription::where('user_id', $request->user()->id)->where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();

        return response()->noContent();
    }
}
