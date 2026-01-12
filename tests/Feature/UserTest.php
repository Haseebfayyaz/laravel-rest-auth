<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Register Tests
test('user can register with valid data', function () {
    $userData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $response = $this->postJson('/api/auth/register', $userData);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'token',
            'token_type',
            'user' => [
                'id',
                'name',
                'email',
            ],
        ])
        ->assertJson([
            'token_type' => 'Bearer',
            'user' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);
});

test('user cannot register with invalid email', function () {
    $userData = [
        'name' => 'John Doe',
        'email' => 'invalid-email',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $response = $this->postJson('/api/auth/register', $userData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('user cannot register with duplicate email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $userData = [
        'name' => 'John Doe',
        'email' => 'existing@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $response = $this->postJson('/api/auth/register', $userData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('user cannot register without password confirmation', function () {
    $userData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
    ];

    $response = $this->postJson('/api/auth/register', $userData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('user can register with role', function () {
    $userData = [
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'admin',
    ];

    $response = $this->postJson('/api/auth/register', $userData);

    $response->assertStatus(201);
    
    $this->assertDatabaseHas('users', [
        'email' => 'admin@example.com',
        'role' => 'admin',
    ]);
});

// Login Tests
test('user can login with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $credentials = [
        'email' => 'test@example.com',
        'password' => 'password123',
    ];

    $response = $this->postJson('/api/auth/login', $credentials);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'token',
            'token_type',
            'user' => [
                'id',
                'name',
                'email',
            ],
        ])
        ->assertJson([
            'token_type' => 'Bearer',
            'user' => [
                'email' => 'test@example.com',
            ],
        ]);
});

test('user cannot login with invalid credentials', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $credentials = [
        'email' => 'test@example.com',
        'password' => 'wrong-password',
    ];

    $response = $this->postJson('/api/auth/login', $credentials);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('user cannot login with non-existent email', function () {
    $credentials = [
        'email' => 'nonexistent@example.com',
        'password' => 'password123',
    ];

    $response = $this->postJson('/api/auth/login', $credentials);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('login requires email and password', function () {
    $response = $this->postJson('/api/auth/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

// Me (Get Current User) Tests
test('authenticated user can get their profile', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/auth/user');

    $response->assertStatus(200)
        ->assertJson([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ])
        ->assertJsonMissing(['password']);
});

test('unauthenticated user cannot get profile', function () {
    $response = $this->getJson('/api/auth/user');

    $response->assertStatus(401);
});

// Update Profile Tests
test('authenticated user can update their profile', function () {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);

    $updateData = [
        'name' => 'New Name',
        'email' => 'new@example.com',
    ];

    $response = $this->actingAs($user, 'sanctum')
        ->putJson('/api/auth/user', $updateData);

    $response->assertStatus(200)
        ->assertJson([
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'New Name',
        'email' => 'new@example.com',
    ]);
});

test('authenticated user can update only name', function () {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'test@example.com',
    ]);

    $updateData = [
        'name' => 'New Name',
    ];

    $response = $this->actingAs($user, 'sanctum')
        ->putJson('/api/auth/user', $updateData);

    $response->assertStatus(200)
        ->assertJson([
            'name' => 'New Name',
            'email' => 'test@example.com', // Should remain unchanged
        ]);
});

test('authenticated user can update password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('oldpassword'),
    ]);

    $updateData = [
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ];

    $response = $this->actingAs($user, 'sanctum')
        ->putJson('/api/auth/user', $updateData);

    $response->assertStatus(200);

    // Verify password was updated
    $user->refresh();
    expect(Hash::check('newpassword123', $user->password))->toBeTrue();
});

test('user cannot update email to existing email', function () {
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);
    $user = User::factory()->create(['email' => 'test@example.com']);

    $updateData = [
        'email' => 'existing@example.com',
    ];

    $response = $this->actingAs($user, 'sanctum')
        ->putJson('/api/auth/user', $updateData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('user can update email to same email', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);

    $updateData = [
        'email' => 'test@example.com',
    ];

    $response = $this->actingAs($user, 'sanctum')
        ->putJson('/api/auth/user', $updateData);

    $response->assertStatus(200);
});

test('unauthenticated user cannot update profile', function () {
    $updateData = [
        'name' => 'New Name',
    ];

    $response = $this->putJson('/api/auth/user', $updateData);

    $response->assertStatus(401);
});

test('password update requires confirmation', function () {
    $user = User::factory()->create();

    $updateData = [
        'password' => 'newpassword123',
    ];

    $response = $this->actingAs($user, 'sanctum')
        ->putJson('/api/auth/user', $updateData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});
