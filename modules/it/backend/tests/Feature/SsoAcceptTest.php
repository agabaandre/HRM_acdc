<?php

namespace Tests\Feature;

use Tests\TestCase;

class SsoAcceptTest extends TestCase
{
    public function test_sso_accept_requires_token(): void
    {
        $this->postJson('/sso/accept', [])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'staff_sso_jwt is required.']);
    }
}
