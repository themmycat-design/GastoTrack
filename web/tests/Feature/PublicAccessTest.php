<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicAccessTest extends TestCase
{
    public function test_welcome_page_is_available_to_visitors(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Ready for a smoother shop day?')
            ->assertSee('The project team shares EAS-built APKs')
            ->assertSee(route('contact'))
            ->assertSee(route('terms'))
            ->assertSee(route('privacy'));
    }

    public function test_welcome_page_uses_a_configured_https_apk_link(): void
    {
        config()->set('services.gastotrack.android_apk_url', 'https://downloads.example.test/gastotrack.apk');

        $this->get('/')
            ->assertOk()
            ->assertSee('Download for Android')
            ->assertSee('https://downloads.example.test/gastotrack.apk')
            ->assertDontSee('The project team shares EAS-built APKs');
    }

    public function test_public_information_pages_are_available_to_visitors(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('paghari-oncc.694.stud@cdd.edu.ph');

        $this->get(route('terms'))
            ->assertOk()
            ->assertSee('Accounts and roles');

        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('Storage and retention');
    }
}
