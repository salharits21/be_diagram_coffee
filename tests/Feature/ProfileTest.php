<?php

use App\Models\User;

beforeEach(function () {
    $this->customer = User::factory()->create([
        'name' => 'Old Name',
        'role' => 'customer',
    ]);
});

describe('Update Profile', function () {
    test('user can update their name', function () {
        $response = $this->actingAs($this->customer)
            ->putJson('/api/user/profile', [
                'name' => 'New Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'New Name');

        $this->customer->refresh();
        expect($this->customer->name)->toBe('New Name');
    });

    test('name is required', function () {
        $response = $this->actingAs($this->customer)
            ->putJson('/api/user/profile', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    test('name max 255 characters', function () {
        $response = $this->actingAs($this->customer)
            ->putJson('/api/user/profile', [
                'name' => str_repeat('a', 256),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    test('unauthenticated cannot update profile', function () {
        $response = $this->putJson('/api/user/profile', [
            'name' => 'Hacker',
        ]);

        $response->assertUnauthorized();
    });
});
