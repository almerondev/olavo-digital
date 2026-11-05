<?php

namespace App\Modules\People\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Models\Student;
use App\Modules\People\Http\Requests\SaveStudentRequest;
use App\Modules\People\Services\PrivacyService;
use App\Modules\People\Services\StudentService;
use App\Modules\Materials\Services\MaterialSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class StudentAdminController extends Controller
{
    private const HISTORY_LIMIT = 30;

    public function __construct(private StudentService $students, private PrivacyService $privacy)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:active,pending,blocked'],
            'course' => ['nullable', 'integer'],
            'group' => ['nullable', 'integer'],
            'deletion' => ['nullable', 'boolean'],
        ]);

        $page = Student::with('user', 'courses', 'groups')
            ->whereHas('user', fn (Builder $users) => $users
                ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
                ->when($filters['deletion'] ?? false, fn (Builder $q) => $q->whereNotNull('deletion_requested_at')))
            ->when($filters['q'] ?? null, function (Builder $query, string $term) {
                $like = MaterialSearch::pattern($term);

                $query->where(fn (Builder $match) => $match
                    ->where('enrollment_code', 'like', $like)
                    ->orWhereHas('user', fn (Builder $users) => $users->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)));
            })
            ->when($filters['course'] ?? null, fn (Builder $q, $id) => $q->whereHas('courses', fn (Builder $courses) => $courses->where('courses.id', $id)))
            ->when($filters['group'] ?? null, fn (Builder $q, $id) => $q->whereHas('groups', fn (Builder $groups) => $groups->where('student_groups.id', $id)))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return response()->json([
            'data' => $page->getCollection()->map(fn (Student $student) => $this->summary($student)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Student $student): JsonResponse
    {
        return response()->json(['data' => $this->detail($student)]);
    }

    public function store(SaveStudentRequest $request): JsonResponse
    {
        $student = $this->students->create($request->validated(), $request->user());

        return response()->json(['data' => $this->detail($student)], 201);
    }

    public function update(SaveStudentRequest $request, Student $student): JsonResponse
    {
        $this->students->update($student, $request->validated(), $request->user());

        return response()->json(['data' => $this->detail($student->refresh())]);
    }

    public function block(Request $request, Student $student): Response
    {
        $this->students->setBlocked($student, true, $request->user());

        return response()->noContent();
    }

    public function unblock(Request $request, Student $student): Response
    {
        $this->students->setBlocked($student, false, $request->user());

        return response()->noContent();
    }

    public function resetPassword(Request $request, Student $student): Response
    {
        $this->students->sendPasswordReset($student, $request->user());

        return response()->noContent();
    }

    public function export(Student $student): JsonResponse
    {
        return response()->json(['data' => $this->privacy->export($student->user)]);
    }

    public function anonymize(Request $request, Student $student): Response
    {
        $this->privacy->anonymize($student, $request->user());

        return response()->noContent();
    }

    private function summary(Student $student): array
    {
        $user = $student->user;

        return [
            'id' => $student->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status->value,
            'enrollment_code' => $student->enrollment_code,
            'courses' => $student->courses->pluck('name'),
            'groups' => $student->groups->pluck('name'),
            'created_at' => $user->created_at->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'deletion_requested_at' => $user->deletion_requested_at?->toIso8601String(),
        ];
    }

    private function detail(Student $student): array
    {
        $student->load('user', 'courses', 'groups');

        $history = DB::table('watch_history')
            ->join('materials', 'materials.id', '=', 'watch_history.material_id')
            ->where('student_id', $student->id)
            ->orderByDesc('started_at')
            ->limit(self::HISTORY_LIMIT)
            ->get(['watch_history.id', 'materials.title', 'materials.slug', 'started_at', 'seconds_watched']);

        return [
            ...$this->summary($student),
            'birth_date' => $student->birth_date?->toDateString(),
            'guardian_name' => $student->guardian_name,
            'guardian_contact' => $student->guardian_contact,
            'course_ids' => $student->courses->modelKeys(),
            'group_ids' => $student->groups->modelKeys(),
            'terms_accepted_at' => $student->user->terms_accepted_at?->toIso8601String(),
            'stats' => [
                'accesses' => DB::table('watch_history')->where('student_id', $student->id)->count(),
                'completed' => DB::table('watch_progress')->where('student_id', $student->id)->whereNotNull('completed_at')->count(),
                'favorites' => DB::table('favorites')->where('student_id', $student->id)->count(),
            ],
            'history' => $history,
        ];
    }
}
