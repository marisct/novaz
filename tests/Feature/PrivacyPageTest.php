<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivacyPageTest extends TestCase
{
    public function test_privacy_policy_is_public_and_linked_from_the_home_and_login_pages(): void
    {
        $this->get('/privacy')->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('in.novaz.app')
            ->assertSee('Account deletion')
            ->assertSee(config('mail.from.address'));

        $this->get('/')->assertOk()->assertSee(route('privacy'));
        $this->get('/login')->assertOk()->assertSee(route('privacy'));
    }
}
