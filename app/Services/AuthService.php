<?php

namespace App\Services;

use App\Events\UserRegistered;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct()
    {
    }

    /**
     * Register a new user
     *
     * @param Request $request
     * @return User
     * @throws ValidationException
     */
    public function register(Request $request): User
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['nullable', Rule::in(['user', 'admin'])],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'] ?? 'user',
        ]);

        // Dispatch event to send email verification
        event(new UserRegistered($user));

        return $user;
    }

    /**
     * Authenticate a user and return the user model
     *
     * @param Request $request
     * @return User
     * @throws ValidationException
     */
    public function login(Request $request): User
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return $user;
    }

    /**
     * Refresh the authentication token for the current user
     *
     * @param User $user
     * @return string
     */
    public function refreshToken(User $user): string
    {
        // Delete the current token
        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        // Create and return new token
        return $user->createToken('auth_token')->plainTextToken;
    }
}
