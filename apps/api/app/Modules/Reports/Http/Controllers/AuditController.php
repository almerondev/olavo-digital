<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Audit\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'actor' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $page = ActivityLog::with('actor:id,name,email')
            ->when($filters['action'] ?? null, fn (Builder $q, $action) => $q->where('action', $action))
            ->when($filters['actor'] ?? null, fn (Builder $q, $term) => $q->whereHas('actor', fn (Builder $actors) => $actors
                ->where('name', 'like', '%'.addcslashes($term, '\\%_').'%')
                ->orWhere('email', 'like', '%'.addcslashes($term, '\\%_').'%')))
            ->when($filters['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        return response()->json([
            'data' => $page->getCollection()->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'actor' => $log->actor?->name,
                'subject' => $log->subject_type ? "{$log->subject_type} #{$log->subject_id}" : null,
                'changes' => $log->changes,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at->toIso8601String(),
            ]),
            'actions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }
}
