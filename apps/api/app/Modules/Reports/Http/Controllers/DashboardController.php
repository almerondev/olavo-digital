<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Models\Student;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Catalog\Models\Course;
use App\Modules\LiveTv\Models\LiveStream;
use App\Modules\Materials\Enums\MaterialStatus;
use App\Modules\Materials\Enums\MaterialType;
use App\Modules\Materials\Models\Material;
use App\Support\Audit\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const CACHE_SECONDS = 120;
    private const TREND_DAYS = 30;
    private const LIST_LIMIT = 6;

    public function __invoke(Request $request): JsonResponse
    {
        $data = Cache::remember('dashboard:numbers', self::CACHE_SECONDS, fn () => $this->numbers());

        $data['recent_activity'] = $request->user()->can('audit.view')
            ? ActivityLog::with('actor:id,name')->latest('id')->limit(self::LIST_LIMIT)->get()
                ->map(fn (ActivityLog $log) => ['id' => $log->id, 'action' => $log->action, 'actor' => $log->actor?->name, 'created_at' => $log->created_at->toIso8601String()])
            : [];

        return response()->json(['data' => $data]);
    }

    /** Só dados puros: o cache do Laravel 13 não aceita objetos. */
    private function numbers(): array
    {
        $students = fn () => Student::whereHas('user');
        $since = now()->subDays(7);

        return [
            'totals' => [
                'students' => $students()->count(),
                'active_students' => $students()->whereHas('user', fn (Builder $users) => $users->where('status', UserStatus::Active))->count(),
                'recent_students' => $students()->whereHas('user', fn (Builder $users) => $users->where('last_login_at', '>=', $since))->count(),
                'teachers' => Teacher::count(),
                'published_materials' => Material::where('status', MaterialStatus::Published)->count(),
                'videos' => Material::where('status', MaterialStatus::Published)->whereIn('type', MaterialType::audiovisual())->count(),
                'views' => DB::table('watch_history')->count(),
                'live_streams' => LiveStream::whereNotNull('ended_at')->count(),
            ],
            'views_by_day' => $this->viewsByDay(),
            'students_by_course' => Course::withCount('students')->orderByDesc('students_count')->get()
                ->map(fn (Course $course) => ['name' => $course->name, 'students' => $course->students_count])->all(),
            'recent_students' => $students()->with('user:id,name,last_login_at')
                ->join('users', 'users.id', '=', 'students.user_id')
                ->whereNotNull('users.last_login_at')
                ->orderByDesc('users.last_login_at')
                ->limit(self::LIST_LIMIT)
                ->get(['students.*'])
                ->map(fn (Student $student) => ['id' => $student->id, 'name' => $student->user->name, 'last_login_at' => $student->user->last_login_at->toIso8601String()])
                ->all(),
            'top_materials' => DB::table('watch_history')
                ->join('materials', 'materials.id', '=', 'watch_history.material_id')
                ->whereNull('materials.deleted_at')
                ->selectRaw('materials.id, materials.title, COUNT(*) as views')
                ->groupBy('materials.id', 'materials.title')
                ->orderByDesc('views')
                ->limit(self::LIST_LIMIT)
                ->get()
                ->map(fn ($row) => ['id' => $row->id, 'title' => $row->title, 'views' => (int) $row->views])
                ->all(),
            'latest_materials' => Material::where('status', MaterialStatus::Published)->with('teacher:id,display_name')->latest('published_at')->limit(self::LIST_LIMIT)->get()
                ->map(fn (Material $material) => ['id' => $material->id, 'title' => $material->title, 'teacher' => $material->teacher?->display_name, 'published_at' => $material->published_at?->toIso8601String()])
                ->all(),
        ];
    }

    /** @return list<array{day: string, views: int}> */
    private function viewsByDay(): array
    {
        $from = now()->subDays(self::TREND_DAYS - 1)->startOfDay();

        $counts = DB::table('watch_history')
            ->where('started_at', '>=', $from)
            ->selectRaw('DATE(started_at) as day, COUNT(*) as views')
            ->groupBy('day')
            ->pluck('views', 'day');

        return collect(range(0, self::TREND_DAYS - 1))->map(function (int $offset) use ($from, $counts) {
            $day = Carbon::parse($from)->addDays($offset)->toDateString();

            return ['day' => $day, 'views' => (int) ($counts[$day] ?? 0)];
        })->all();
    }
}
