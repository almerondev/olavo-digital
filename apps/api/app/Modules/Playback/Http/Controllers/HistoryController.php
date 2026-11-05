<?php

namespace App\Modules\Playback\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Playback\Http\Resources\HistoryEntryResource;
use App\Modules\Playback\Models\WatchHistory;
use App\Modules\Playback\Services\StudentLibrary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class HistoryController extends Controller
{
    public function __construct(private StudentLibrary $library)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $student = $this->library->requireStudent($request->user());

        return HistoryEntryResource::collection(
            WatchHistory::query()
                ->with('material.teacher', 'material.cover', 'material.banner', 'material.categories')
                ->where('student_id', $student->id)
                ->whereNull('hidden_at')
                ->whereHas('material', fn ($materials) => $materials->visibleTo($request->user()))
                ->orderByDesc('last_event_at')
                ->paginate(30)
        );
    }

    /** Limpar só esconde do aluno: as métricas de acesso continuam válidas. */
    public function clear(Request $request): Response
    {
        $student = $this->library->requireStudent($request->user());

        WatchHistory::where('student_id', $student->id)->whereNull('hidden_at')->update(['hidden_at' => now()]);

        return response()->noContent();
    }

    public function destroy(Request $request, int $entry): Response
    {
        $student = $this->library->requireStudent($request->user());

        WatchHistory::where('student_id', $student->id)->whereKey($entry)->update(['hidden_at' => now()]);

        return response()->noContent();
    }
}
