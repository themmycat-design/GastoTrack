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
            ->assertSee('Download APK')
            ->assertSee(route('download-app'))
            ->assertSee(route('contact'))
            ->assertSee(route('terms'))
            ->assertSee(route('privacy'));
    }

    public function test_welcome_page_uses_a_configured_https_apk_link(): void
    {
        config()->set('services.gastotrack.android_apk_url', 'https://downloads.example.test/gastotrack.apk');

        $this->get(route('download-app'))
            ->assertOk()
            ->assertSee('Download APK')
            ->assertSee('https://downloads.example.test/gastotrack.apk')
            ->assertSee('QR code for the GastoTrack Staff APK download')
            ->assertSee('data:image/svg+xml;base64,')
            ->assertDontSee('The APK download link is not available here yet.');
    }

    public function test_download_guide_explains_how_to_get_the_apk_when_no_link_is_configured(): void
    {
        config()->set('services.gastotrack.android_apk_url', null);

        $this->get(route('download-app'))
            ->assertOk()
            ->assertSee('The APK download link is not available here yet.')
            ->assertSee('Install on your phone')
            ->assertDontSee('data:image/svg+xml;base64,')
            ->assertSee('Blocked by Google Play Protect?')
            ->assertSee('notification access to detect GCash and Maya')
            ->assertSee('downloaded through a browser can be blocked')
            ->assertSee('Request the Play testing link')
            ->assertSee('Keep Play Protect enabled')
            ->assertSee(route('contact'));
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
