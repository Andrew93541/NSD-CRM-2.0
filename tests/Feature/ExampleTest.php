<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guest_is_redirected_to_login_on_root(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }
}
