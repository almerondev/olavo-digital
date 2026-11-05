<?php

namespace App\Modules\Playback\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Services\MaterialAccessService;
use App\Modules\Playback\Services\PlaybackService;
use App\Modules\Playback\Services\StudentLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlaybackController extends Controller
{
    public function __construct(
        private PlaybackService $playback,
        private StudentLibrary $library,
        private MaterialAccessService $access,
    ) {
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        return response()->json(['data' => $this->playback->source($this->find($request, $slug), $request->user())]);
    }

    public function progress(Request $request, string $slug): Response
    {
        $data = $request->validate([
            'position' => ['required', 'integer', 'min:0'],
            'duration' => ['nullable', 'integer', 'min:1'],
            'watched' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->playback->saveProgress(
            $this->library->requireStudent($request->user()),
            $this->find($request, $slug),
            $data['position'],
            $data['duration'] ?? null,
            $data['watched'] ?? 0,
        );

        return response()->noContent();
    }

    public function complete(Request $request, string $slug): Response
    {
        $this->playback->complete($this->library->requireStudent($request->user()), $this->find($request, $slug));

        return response()->noContent();
    }

    /** Registra no histórico o acesso a PDFs, imagens e demais conteúdos sem reprodução. */
    public function access(Request $request, string $slug): Response
    {
        if ($request->user()->student) {
            $this->playback->touchHistory($request->user()->student, $this->find($request, $slug));
        }

        return response()->noContent();
    }

    private function find(Request $request, string $slug): Material
    {
        $material = Material::where('slug', $slug)->firstOrFail();

        abort_unless($this->access->canView($request->user(), $material), 404);

        return $material;
    }
}
