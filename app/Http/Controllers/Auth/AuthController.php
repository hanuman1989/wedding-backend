<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    // Register a new user
    public function register(RegisterRequest $request)
    {
        $user = User::create($request->validated());

        $token = $user->createToken('user-auth-token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Registration successful.',
            'data' => [
                'user' => (new UserResource($user))->resolve(),
                'token' => $token,
            ],
        ], 201);
    }

    // Login user
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid email or password.',
                'data' => null,
            ], 401);
        }

        $token = $user->createToken('user-auth-token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => (new UserResource($user))->resolve(),
                'token' => $token,
            ],
        ]);
    }

    // Logout user
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Logout successful',
            'data' => [],
        ]);
    }

    // Get the authenticated user's profile
    public function me(Request $request)
    {
        return response()->json([
            'status' => true,
            'message' => '',
            'data' => (new UserResource($request->user()))->resolve(),
        ]);
    }

    // Send a password reset link to the given email
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $status = Password::sendResetLink($request->only('email'));

        return response()->json([
            'status' => $status === Password::RESET_LINK_SENT,
            'message' => __($status),
            'data' => [],
        ], $status === Password::RESET_LINK_SENT ? 200 : 400);
    }

    // Reset the user's password using a valid token
    public function resetPassword(ResetPasswordRequest $request)
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();

                $user->tokens()->delete();
            }
        );

        return response()->json([
            'status' => $status === Password::PASSWORD_RESET,
            'message' => __($status),
            'data' => [],
        ], $status === Password::PASSWORD_RESET ? 200 : 400);
    }
}
