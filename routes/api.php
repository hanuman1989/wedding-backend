<?php
use Illuminate\Http\Request;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::post('login', [AdminUserController::class, 'login'])->name('login');

    Route::middleware(['auth:sanctum', 'admin.auth'])->group(function () {
    Route::post('logout', [AdminUserController::class, 'logout']);
    Route::get('/me', function (Request $request) {
                return response()->json([
                    'status' => true,
                    'message' => '',
                    'data' => (new \App\Http\Resources\AdminUserResource($request->user()))->resolve()
                ], 200);
            });

        //Route::get('me', [AuthController::class, 'me'])->name('me');
        //Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::apiResource('admin-users', AdminUserController::class);
    });
});
