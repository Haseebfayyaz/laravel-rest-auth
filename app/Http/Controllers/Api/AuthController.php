<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use App\Services\AuthService;

class AuthController extends Controller
{
    public function __construct(protected AuthService $authService)
    {
    }

    public function register(Request $request)
    {
        $user = $this->authService->register($request);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $user = $this->authService->login($request);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'password' => ['sometimes', 'confirmed', Password::defaults()],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json($user->fresh());
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out']);
    }

    public function refresh(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $newToken = $this->authService->refreshToken($user);

        return response()->json([
            'token' => $newToken,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Mark the user's email as verified.
     */
    public function verifyEmail(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
            ], 400);
        }

        if ($user->markEmailAsVerified()) {
            return response()->json([
                'message' => 'Email verified successfully.',
            ]);
        }

        return response()->json([
            'message' => 'Email verification failed.',
        ], 400);
    }

    /**
     * Resend the email verification notification.
     */
    public function resendVerificationEmail(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification email sent successfully.',
        ]);
    }

    /**
     * Verify email from the signed URL in the email.
     */
    public function verifyEmailFromLink(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'message' => 'Invalid verification link.',
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
            ], 400);
        }

        if ($user->markEmailAsVerified()) {
            return response()->json([
                'message' => 'Email verified successfully.',
            ]);
        }

        return response()->json([
            'message' => 'Email verification failed.',
        ], 400);
    }
}

