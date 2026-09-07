<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialLoginCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialAuthController extends Controller
{
    private const PROVIDERS = [
        'google',
        'facebook',
    ];

    /**
     * Redirect user to Google/Facebook.
     */
    public function redirect(string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        return Socialite::driver($provider)
            ->stateless()
            ->redirect();
    }

    /**
     * OAuth provider callback.
     */
    public function callback(string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)
                ->stateless()
                ->user();

            if (! $socialUser->getId()) {
                throw new \RuntimeException(
                    'Social provider did not return a valid user ID.'
                );
            }

            $user = DB::transaction(function () use (
                $provider,
                $socialUser
            ) {
                /*
                 * 1. Existing social account
                 */
                $socialAccount = SocialAccount::query()
                    ->where('provider', $provider)
                    ->where(
                        'provider_id',
                        $socialUser->getId()
                    )
                    ->first();

                if ($socialAccount) {
                    return $socialAccount->user;
                }

                /*
                 * 2. Existing user by email
                 */
                $user = null;

                $email = $socialUser->getEmail();

                if ($email) {
                    $user = User::query()
                        ->where('email', $email)
                        ->first();
                }

                /*
                 * 3. Create new user
                 */
                if (! $user) {
                    $name = $socialUser->getName()
                        ?: $socialUser->getNickname()
                        ?: 'User';

                    $user = User::create([
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make(
                            Str::random(64)
                        ),
                        'email_verified_at' => now(),
                    ]);
                }

                /*
                 * 4. Attach social account
                 */
                SocialAccount::create([
                    'user_id' => $user->id,
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'provider_email' => $email,
                    'provider_name' => $socialUser->getName(),
                    'provider_avatar' => $socialUser->getAvatar(),
                ]);

                return $user;
            });

            /*
             * Create your existing Sanctum bearer token.
             */
            $token = $user
                ->createToken('frontend')
                ->plainTextToken;

            /*
             * Create short-lived exchange code.
             */
            $rawCode = Str::random(64);

            SocialLoginCode::create([
                'user_id' => $user->id,
                'code_hash' => hash(
                    'sha256',
                    $rawCode
                ),
                'expires_at' => now()->addMinute(),
            ]);

            /*
             * IMPORTANT:
             * Do not send the Sanctum token in the URL.
             *
             * Send only the temporary code.
             */
            return redirect()->away(
                rtrim(
                    config('app.frontend_url'),
                    '/'
                )
                    .'/auth/social/callback?code='
                    .urlencode($rawCode)
            );
        } catch (Throwable $exception) {

            report($exception);

            return redirect()->away(
                rtrim(
                    config('app.frontend_url'),
                    '/'
                )
                    .'/login?social_login=failed'
            );
        }
    }

    /**
     * Exchange one-time social login code
     * for Sanctum bearer token.
     */
    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'size:64',
            ],
        ]);

        $hash = hash(
            'sha256',
            $validated['code']
        );

        $loginCode = SocialLoginCode::query()
            ->where('code_hash', $hash)
            ->whereNull('used_at')
            ->where(
                'expires_at',
                '>',
                now()
            )
            ->first();

        if (! $loginCode) {
            return response()->json([
                'message' => 'Invalid or expired login code.',
            ], 401);
        }

        /*
         * Mark code used BEFORE returning token.
         *
         * This prevents the same code being exchanged twice.
         */
        $loginCode->update([
            'used_at' => now(),
        ]);

        $user = $loginCode->user;

        /*
         * Create Sanctum token.
         *
         * This is the same authentication mechanism
         * you are already using for normal login.
         */
        $token = $user
            ->createToken('frontend')
            ->plainTextToken;

        return response()->json([
            'message' => 'Social login successful.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    private function validateProvider(
        string $provider
    ): void {
        abort_unless(
            in_array(
                $provider,
                self::PROVIDERS,
                true
            ),
            404
        );
    }
}
