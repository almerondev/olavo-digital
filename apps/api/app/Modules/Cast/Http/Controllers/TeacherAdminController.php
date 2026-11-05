<?php

namespace App\Modules\Cast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cast\Http\Requests\SaveTeacherRequest;
use App\Modules\Cast\Http\Resources\TeacherAdminResource;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Cast\Services\CastService;
use App\Modules\Settings\Services\SettingsService;
use App\Support\Audit\Audit;
use App\Support\Http\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class TeacherAdminController extends Controller
{
    private const RELATIONS = ['user', 'photo', 'banner', 'categories', 'socialLinks'];

    public function __construct(private CastService $cast, private SettingsService $settings)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        return TeacherAdminResource::collection(
            Teacher::with(self::RELATIONS)->withCount('materials')->orderBy('sort_order')->orderBy('display_name')->get()
        );
    }

    public function store(SaveTeacherRequest $request): JsonResponse
    {
        $teacher = $this->cast->create($request->validated(), $request->user());

        return (new TeacherAdminResource($teacher->load(self::RELATIONS)->loadCount('materials')))->response()->setStatusCode(201);
    }

    public function update(SaveTeacherRequest $request, Teacher $teacher): TeacherAdminResource
    {
        $this->cast->update($teacher, $request->validated(), $request->user());

        return new TeacherAdminResource($teacher->refresh()->load(self::RELATIONS)->loadCount('materials'));
    }

    public function destroy(Request $request, Teacher $teacher): Response
    {
        if ($teacher->materials()->exists()) {
            throw new ApiException('Este professor tem materiais. Desative-o ou transfira os materiais antes.', 'teacher_has_materials', 409);
        }

        $teacher->delete();
        Audit::record('teacher.deleted', $teacher, actor: $request->user());

        return response()->noContent();
    }

    public function publishPolicy(): JsonResponse
    {
        return response()->json(['data' => ['mode' => $this->settings->get(Teacher::PUBLISH_SETTING, 'approval')]]);
    }

    public function updatePublishPolicy(Request $request): JsonResponse
    {
        $mode = $request->validate(['mode' => ['required', Rule::in(['immediate', 'approval'])]])['mode'];

        $this->settings->set(Teacher::PUBLISH_SETTING, $mode);
        Audit::record('teacher.publish_policy', null, ['mode' => $mode]);

        return response()->json(['data' => ['mode' => $mode]]);
    }
}
