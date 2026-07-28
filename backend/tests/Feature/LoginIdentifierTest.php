<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginIdentifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_username_or_identity_number(): void
    {
        User::factory()->create([
            'username' => 'collector01',
            'identity_number' => '048099001234',
            'password' => 'Secret@123',
            'is_active' => true,
        ]);

        foreach (['collector01', '048099001234'] as $identifier) {
            $this->postJson('/api/v1/auth/login', [
                'identifier' => $identifier,
                'password' => 'Secret@123',
            ])->assertOk()->assertJsonPath('success', true)->assertJsonStructure(['data' => ['token']]);
        }
    }
}
