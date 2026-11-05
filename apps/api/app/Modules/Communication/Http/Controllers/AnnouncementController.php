<?php

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Http\Resources\AnnouncementResource;
use App\Modules\Communication\Models\Announcement;
use App\Modules\Communication\Models\AnnouncementRead;
use App\Modules\Communication\Services\AnnouncementBoard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AnnouncementController extends Controller
{
    public function index(Request $request, AnnouncementBoard $board): AnonymousResourceCollection
    {
        return AnnouncementResource::collection($board->forUser($request->user())->paginate(20));
    }

    public function read(Request $request, int $announcement): Response
    {
        $found = Announcement::visibleTo($request->user())->findOrFail($announcement);

        AnnouncementRead::firstOrCreate(
            ['announcement_id' => $found->id, 'user_id' => $request->user()->id],
            ['read_at' => now()],
        );

        return response()->noContent();
    }
}
