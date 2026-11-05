<?php

use App\Modules\Access\Http\Controllers\PermissionController;
use App\Modules\Access\Http\Controllers\RoleController;
use App\Modules\Auth\Http\Controllers\LoginController;
use App\Modules\Auth\Http\Controllers\MeController;
use App\Modules\Auth\Http\Controllers\PasswordResetController;
use App\Modules\Auth\Http\Controllers\SessionController;
use App\Modules\Cast\Http\Controllers\CastController;
use App\Modules\Cast\Http\Controllers\TeacherAdminController;
use App\Modules\Cast\Http\Controllers\TeacherPanelController;
use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\CategoryTypeController;
use App\Modules\Catalog\Http\Controllers\CourseController;
use App\Modules\Communication\Http\Controllers\AnnouncementAdminController;
use App\Modules\Communication\Http\Controllers\AnnouncementController;
use App\Modules\Communication\Http\Controllers\NotificationAdminController;
use App\Modules\Communication\Http\Controllers\NotificationController;
use App\Modules\Home\Http\Controllers\HomeBuilderController;
use App\Modules\Home\Http\Controllers\HomeController;
use App\Modules\LiveTv\Http\Controllers\LiveAdminController;
use App\Modules\LiveTv\Http\Controllers\TvController;
use App\Modules\Materials\Http\Controllers\CatalogController;
use App\Modules\Materials\Http\Controllers\MaterialController;
use App\Modules\Materials\Http\Controllers\OptionsController;
use App\Modules\Materials\Http\Controllers\SearchController;
use App\Modules\Playback\Http\Controllers\FavoriteController;
use App\Modules\Playback\Http\Controllers\HistoryController;
use App\Modules\Playback\Http\Controllers\PlaybackController;
use App\Modules\People\Http\Controllers\GroupAdminController;
use App\Modules\People\Http\Controllers\PrivacyController;
use App\Modules\People\Http\Controllers\StudentAdminController;
use App\Modules\Reports\Http\Controllers\AuditController;
use App\Modules\Reports\Http\Controllers\DashboardController;
use App\Modules\Settings\Http\Controllers\PublicConfigController;
use App\Modules\Settings\Http\Controllers\SettingsAdminController;
use App\Modules\Uploads\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('config', PublicConfigController::class);

Route::get('uploads/{upload}/file', [UploadController::class, 'file'])->middleware('signed')->name('uploads.file');

Route::prefix('auth')->group(function () {
    Route::post('login', [LoginController::class, 'store']);
    Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:5,1');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1');
});

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('auth/logout', [LoginController::class, 'destroy']);

    Route::post('me/terms/accept', [PrivacyController::class, 'acceptTerms']);
    Route::get('me/export', [PrivacyController::class, 'export'])->middleware('throttle:5,1');
    Route::post('me/deletion-request', [PrivacyController::class, 'requestDeletion']);
    Route::delete('me/deletion-request', [PrivacyController::class, 'cancelDeletion']);

    Route::prefix('me')->group(function () {
        Route::get('/', [MeController::class, 'show']);
        Route::patch('/', [MeController::class, 'update']);
        Route::put('password', [MeController::class, 'updatePassword']);
        Route::get('sessions', [SessionController::class, 'index']);
        Route::delete('sessions/{session}', [SessionController::class, 'destroy']);
    });

    Route::middleware('can:content.view')->group(function () {
        Route::get('home', [HomeController::class, 'show']);
        Route::get('home/sections/{section}/items', [HomeController::class, 'items']);

        Route::get('tv/now', [TvController::class, 'now']);
        Route::get('tv/schedule', [TvController::class, 'schedule']);

        Route::get('teachers', [CastController::class, 'index']);
        Route::get('teachers/{slug}', [CastController::class, 'show']);

        Route::get('catalog/filters', [CatalogController::class, 'filters']);
        Route::get('materials', [CatalogController::class, 'index']);
        Route::get('materials/{slug}', [CatalogController::class, 'show']);
        Route::get('materials/{slug}/related', [CatalogController::class, 'related']);
        Route::get('materials/{slug}/file', [CatalogController::class, 'file']);
        Route::get('materials/{slug}/playback', [PlaybackController::class, 'show']);
        Route::put('materials/{slug}/progress', [PlaybackController::class, 'progress'])->middleware('throttle:30,1');
        Route::post('materials/{slug}/complete', [PlaybackController::class, 'complete']);
        Route::post('materials/{slug}/access', [PlaybackController::class, 'access']);

        Route::get('search', SearchController::class)->middleware('throttle:60,1');

        Route::get('announcements', [AnnouncementController::class, 'index']);
        Route::post('announcements/{announcement}/read', [AnnouncementController::class, 'read'])->whereNumber('announcement');

        Route::prefix('me/notifications')->controller(NotificationController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('unread-count', 'unreadCount');
            Route::post('read-all', 'readAll');
            Route::post('{notification}/read', 'read')->whereNumber('notification');
        });
        Route::post('me/push-devices', [NotificationController::class, 'registerDevice']);
        Route::delete('me/push-devices', [NotificationController::class, 'removeDevice']);

        Route::get('me/favorites', [FavoriteController::class, 'index']);
        Route::put('me/favorites/{slug}', [FavoriteController::class, 'store']);
        Route::delete('me/favorites/{slug}', [FavoriteController::class, 'destroy']);

        Route::get('me/history', [HistoryController::class, 'index']);
        Route::delete('me/history', [HistoryController::class, 'clear']);
        Route::delete('me/history/{entry}', [HistoryController::class, 'destroy'])->whereNumber('entry');
    });

    Route::prefix('uploads')->group(function () {
        Route::post('/', [UploadController::class, 'store']);
        Route::put('{upload}/content', [UploadController::class, 'putContent'])->middleware('signed')->name('uploads.content');
        Route::post('{upload}/complete', [UploadController::class, 'complete']);
        Route::get('{upload}/access', [UploadController::class, 'access']);
        Route::get('{upload}', [UploadController::class, 'show']);
    });

    Route::prefix('teacher')->middleware('can:panel.teacher')->controller(TeacherPanelController::class)->group(function () {
        Route::get('overview', 'overview');
        Route::get('materials', 'materials');
        Route::post('materials', 'storeMaterial');
        Route::get('materials/{material}', 'showMaterial');
        Route::put('materials/{material}', 'updateMaterial');
        Route::delete('materials/{material}', 'destroyMaterial');
        Route::post('materials/{material}/release', 'releaseMaterial');
        Route::get('categories', 'categories');
        Route::get('options/teachers', 'teachers');
        Route::get('options/groups', 'groups');
        Route::get('profile', 'profile');
        Route::put('profile', 'updateProfile');
    });

    Route::prefix('admin')->middleware('can:panel.admin')->group(function () {
        Route::get('dashboard', DashboardController::class)->middleware('can:analytics.view_all');
        Route::get('audit', AuditController::class)->middleware('can:audit.view');

        Route::middleware('can:settings.manage')->group(function () {
            Route::get('settings', [SettingsAdminController::class, 'show']);
            Route::put('settings', [SettingsAdminController::class, 'update']);
        });

        Route::middleware('can:students.manage')->group(function () {
            Route::prefix('students/{student}')->controller(StudentAdminController::class)->group(function () {
                Route::post('block', 'block');
                Route::post('unblock', 'unblock');
                Route::post('reset-password', 'resetPassword')->middleware('throttle:10,1');
                Route::get('export', 'export');
                Route::post('anonymize', 'anonymize');
            });
            Route::apiResource('students', StudentAdminController::class)->except('destroy');
            Route::apiResource('groups', GroupAdminController::class)->except('show');
        });

        Route::middleware('can:announcements.manage')->group(function () {
            Route::get('announcements/{announcement}/reads', [AnnouncementAdminController::class, 'reads']);
            Route::apiResource('announcements', AnnouncementAdminController::class)->except('show');
        });

        Route::prefix('notifications')->middleware('can:notifications.send')->controller(NotificationAdminController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store')->middleware('throttle:30,1');
            Route::get('recipients', 'recipients');
        });

        Route::prefix('live')->middleware('can:live.manage')->controller(LiveAdminController::class)->group(function () {
            Route::get('settings', 'settings');
            Route::put('settings', 'updateSettings');
            Route::post('go-live', 'goLive');
            Route::get('schedules', 'schedules');
            Route::post('schedules', 'storeSchedule');
            Route::put('schedules/{schedule}', 'updateSchedule');
            Route::post('schedules/{schedule}/cancel', 'cancelSchedule');
            Route::post('schedules/{schedule}/start', 'startSchedule');
            Route::get('streams', 'streams');
            Route::post('streams/{stream}/end', 'endStream');
        });

        Route::middleware('can:teachers.manage')->group(function () {
            Route::get('teachers/publish-policy', [TeacherAdminController::class, 'publishPolicy']);
            Route::put('teachers/publish-policy', [TeacherAdminController::class, 'updatePublishPolicy']);
            Route::apiResource('teachers', TeacherAdminController::class)->except('show');
        });
        Route::prefix('home')->middleware('can:home.manage')->controller(HomeBuilderController::class)->group(function () {
            Route::get('versions', 'versions');
            Route::post('draft', 'draft');
            Route::post('layouts/{layout}/publish', 'publish');
            Route::post('layouts/{layout}/restore', 'restore');
            Route::delete('layouts/{layout}', 'discard');
            Route::get('layouts/{layout}/preview', 'preview');
            Route::post('layouts/{layout}/sections', 'storeSection');
            Route::put('layouts/{layout}/sections/order', 'orderSections');
            Route::put('sections/{section}', 'updateSection');
            Route::delete('sections/{section}', 'destroySection');
            Route::post('layouts/{layout}/banners', 'storeBanner');
            Route::put('banners/{banner}', 'updateBanner');
            Route::delete('banners/{banner}', 'destroyBanner');
        });

        Route::middleware('can:categories.manage')->group(function () {
            Route::apiResource('courses', CourseController::class)->except('show');
            Route::post('categories/reorder', [CategoryController::class, 'reorder']);
            Route::apiResource('categories', CategoryController::class)->except('show');
            Route::apiResource('category-types', CategoryTypeController::class)->except('show')
                ->parameters(['category-types' => 'categoryType']);
        });

        Route::middleware('can:materials.manage_all')->group(function () {
            Route::post('materials/bulk', [MaterialController::class, 'bulk'])->middleware('can:materials.bulk');
            Route::post('materials/{material}/duplicate', [MaterialController::class, 'duplicate']);
            Route::post('materials/{material}/{action}', [MaterialController::class, 'transition'])
                ->whereIn('action', ['release', 'approve', 'reject', 'unschedule', 'archive', 'restore']);
            Route::apiResource('materials', MaterialController::class);

            Route::get('options/teachers', [OptionsController::class, 'teachers']);
            Route::get('options/groups', [OptionsController::class, 'groups']);
            Route::get('options/tags', [OptionsController::class, 'tags']);
        });

        Route::middleware('can:roles.manage')->group(function () {
            Route::get('permissions', [PermissionController::class, 'index']);
            Route::apiResource('roles', RoleController::class)->except('show');
        });
    });
});
