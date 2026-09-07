<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully.',
            'data' => (new UserResource($user))->resolve(),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill([
            'password' => $request->string('password')->toString(),
        ])->save();

        $user->tokens()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Password updated successfully.',
            'data' => [],
        ]);
    }
}
