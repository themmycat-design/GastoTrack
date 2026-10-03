<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicAccessTest extends TestCase
{
    public function test_welcome_page_is_available_to_visitors(): void
    {
        $this->get('/')
            ->assertOk();
    }
}
