<?php

use App\Modules\Access\Http\Controllers\PermissionController;
use App\Modules\Access\Http\Controllers\RoleController;
use App\Modules\Auth\Http\Controllers\LoginController;
use App\Modules\Auth\Http\Controllers\MeController;
use App\Modules\Auth\Http\Controllers\PasswordResetController;
use App\Modules\Auth\Http\Controllers\SessionController;
use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\CategoryTypeController;
use App\Modules\Home\Http\Controllers\HomeBuilderController;
use App\Modules\Home\Http\Controllers\HomeController;
use App\Modules\Materials\Http\Controllers\CatalogController;
use App\Modules\Materials\Http\Controllers\MaterialController;
use App\Modules\Materials\Http\Controllers\OptionsController;
use App\Modules\Playback\Http\Controllers\FavoriteController;
use App\Modules\Playback\Http\Controllers\HistoryController;
use App\Modules\Playback\Http\Controllers\PlaybackController;
use App\Modules\Settings\Http\Controllers\PublicConfigController;
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

        Route::get('catalog/filters', [CatalogController::class, 'filters']);
        Route::get('materials', [CatalogController::class, 'index']);
        Route::get('materials/{slug}', [CatalogController::class, 'show']);
        Route::get('materials/{slug}/related', [CatalogController::class, 'related']);
        Route::get('materials/{slug}/file', [CatalogController::class, 'file']);
        Route::get('materials/{slug}/playback', [PlaybackController::class, 'show']);
        Route::put('materials/{slug}/progress', [PlaybackController::class, 'progress'])->middleware('throttle:30,1');
        Route::post('materials/{slug}/complete', [PlaybackController::class, 'complete']);
        Route::post('materials/{slug}/access', [PlaybackController::class, 'access']);

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

    Route::prefix('admin')->middleware('can:panel.admin')->group(function () {
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
