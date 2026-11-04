<?php

namespace App\Modules\Uploads\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Uploads\Enums\UploadPurpose;
use App\Modules\Uploads\Enums\UploadStatus;
use App\Modules\Uploads\Http\Requests\StartUploadRequest;
use App\Modules\Uploads\Http\Resources\UploadResource;
use App\Modules\Uploads\Models\Upload;
use App\Modules\Uploads\Services\UploadService;
use App\Modules\Uploads\Services\UploadStorage;
use App\Support\Http\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UploadController extends Controller
{
    private const UPLOADER_PERMISSIONS = ['materials.create', 'categories.manage', 'teachers.manage', 'cast.update'];

    public function __construct(private UploadService $uploads)
    {
    }

    public function store(StartUploadRequest $request): JsonResponse
    {
        Gate::authorize('uploads-files');

        $result = $this->uploads->start(
            $request->user(),
            UploadPurpose::from($request->validated('purpose')),
            $request->validated('name'),
            $request->validated('size'),
        );

        return response()->json([
            'data' => new UploadResource($result['upload']),
            'target' => $result['target'],
        ], 201);
    }

    /** Equivalente local da URL pré-assinada do R2. O caminho assinado já limita quem pode gravar. */
    public function putContent(Request $request, Upload $upload): Response
    {
        $declared = (int) $request->header('Content-Length');

        if ($upload->status !== UploadStatus::Pending) {
            throw new ApiException('Este arquivo já foi enviado.', 'upload_already_completed', 409);
        }

        if ($declared > $this->uploads->limitInBytes($upload->file_class)) {
            throw new ApiException('O arquivo excede o limite permitido.', 'upload_too_large', 413);
        }

        Storage::disk($upload->disk)->writeStream($upload->object_key, $request->getContent(true));

        return response()->noContent();
    }

    public function complete(Request $request, Upload $upload): UploadResource
    {
        $this->ensureOwner($request, $upload);

        return new UploadResource($this->uploads->complete($upload)->refresh());
    }

    public function show(Request $request, Upload $upload): UploadResource
    {
        $this->ensureOwner($request, $upload);

        return new UploadResource($upload);
    }

    /** URL temporária para a pré-visualização no painel. */
    public function access(Request $request, Upload $upload, UploadStorage $storage): JsonResponse
    {
        Gate::authorize('uploads-files');

        if (! $upload->isReady()) {
            throw new ApiException('Este arquivo ainda não está disponível.', 'upload_not_ready', 409);
        }

        return response()->json(['data' => ['url' => $storage->temporaryUrl($upload)]]);
    }

    /** Equivalente local da leitura pré-assinada: só responde a URLs válidas e não expiradas. */
    public function file(Upload $upload): StreamedResponse
    {
        return Storage::disk($upload->disk)->response($upload->object_key, $upload->original_name, [
            'Content-Type' => $upload->mime_type ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function ensureOwner(Request $request, Upload $upload): void
    {
        abort_unless($upload->uploaded_by === $request->user()->id, 404);
    }

    /** @return list<string> */
    public static function uploaderPermissions(): array
    {
        return self::UPLOADER_PERMISSIONS;
    }
}
