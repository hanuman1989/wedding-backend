<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Auth\AuthController as UserAuthController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeddingController;
use App\Http\Controllers\WeddingImageController;
use App\Http\Controllers\WeddingStepController;
use App\Http\Controllers\WeddingListController;
use App\Http\Resources\AdminUserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('register', [UserAuthController::class, 'testRegister'])->name('testRegister');
Route::post('register', [UserAuthController::class, 'register'])->name('register');
Route::post('login', [UserAuthController::class, 'login'])->name('login');
Route::post('forgot-password', [UserAuthController::class, 'forgotPassword'])->name('password.email');
Route::post('reset-password', [UserAuthController::class, 'resetPassword'])->name('password.update');
Route::get('/wedding-list', [WeddingListController::class, 'index'])->name('weddinglist.index');
Route::get('/popular-weddings', [WeddingListController::class, 'popular'])->name('weddinglist.popular');

Route::middleware(['auth:sanctum', 'user.auth'])->group(function () {
    Route::post('logout', [UserAuthController::class, 'logout'])->name('logout');
    Route::get('me', [UserAuthController::class, 'me'])->name('me');
    Route::put('profile', [UserController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [UserController::class, 'updatePassword'])->name('profile.password.update');

    Route::get('/my-weddings', [WeddingController::class, 'index'])->name('weddings.index');
    Route::post('/weddings', [WeddingController::class, 'store']);
    Route::get('/weddings/{wedding}', [WeddingController::class, 'show']);
    Route::put('/weddings/{wedding}', [WeddingController::class, 'update']);
    Route::patch('/weddings/{wedding}', [WeddingController::class, 'update']);
    Route::patch('/weddings/{wedding}/partner-details', [WeddingStepController::class, 'updatePartnerDetails'])->name('weddings.partner-details.update');
    Route::patch('/weddings/{wedding}/story', [WeddingStepController::class, 'updateStory'])->name('weddings.story.update');
    Route::patch('/weddings/{wedding}/details', [WeddingStepController::class, 'updateWeddingDetails'])->name('weddings.details.update');

    Route::post('/weddings/{wedding}/images', [WeddingImageController::class, 'store'])->name('weddings.images.store');

    Route::patch('/weddings/{wedding}/images/reorder', [WeddingImageController::class, 'reorder'])->name('weddings.images.reorder');
    Route::delete('/weddings/{wedding}/images/{image}', [WeddingImageController::class, 'destroy'])->name('weddings.images.destroy');
    Route::patch('/weddings/{wedding}/images/reorder', [WeddingImageController::class, 'reorder'])->name('weddings.images.reorder');

    Route::post('/weddings/{wedding}/submit', [WeddingController::class, 'submit']);

    Route::delete('weddings/{wedding}', [WeddingController::class, 'destroy'])->name('weddings.destroy');

});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::post('login', [AdminUserController::class, 'login'])->name('login');

    Route::middleware(['auth:sanctum', 'admin.auth'])->group(function () {
        Route::post('logout', [AdminUserController::class, 'logout']);
        Route::get('/me', function (Request $request) {
            return response()->json([
                'status' => true,
                'message' => '',
                'data' => (new AdminUserResource($request->user()))->resolve(),
            ], 200);
        });

        // Route::get('me', [AuthController::class, 'me'])->name('me');
        // Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::apiResource('admin-users', AdminUserController::class);
    });
});

Route::prefix('auth/social')->group(function () {

    /*
     * Start OAuth
     */
    Route::get(
        '/{provider}/redirect',
        [SocialAuthController::class, 'redirect']
    );

    Route::get(
        '/{provider}',
        [SocialAuthController::class, 'redirect']
    );

    /*
     * Google/Facebook callback
     */
    Route::get(
        '/{provider}/callback',
        [SocialAuthController::class, 'callback']
    );

    /*
     * Next.js exchanges temporary code
     * for Sanctum bearer token.
     */
    Route::post(
        '/exchange',
        [SocialAuthController::class, 'exchange']
    );
});
