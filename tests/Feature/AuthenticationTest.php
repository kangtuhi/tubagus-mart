<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest can view the premium login screen', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Sign in to your workspace');
});

test('active user can sign in and is redirected to the admin dashboard', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $this->post('/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ])->assertRedirect('/admin/dashboard');

    $this->assertAuthenticatedAs($user);
});

test('invalid credentials are rejected', function () {
    User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $this->from('/login')
        ->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('inactive users cannot sign in', function () {
    User::factory()->inactive()->create([
        'email' => 'inactive@example.com',
        'password' => 'password',
    ]);

    $this->from('/login')
        ->post('/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('authenticated user can sign out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});
