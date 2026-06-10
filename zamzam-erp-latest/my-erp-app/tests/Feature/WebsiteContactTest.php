<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_stores_message(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '01700000000',
            'subject' => 'ERP inquiry',
            'message' => 'I want to know more about ZamZam ERP.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('contact_status');

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'customer@example.com',
            'status' => 'new',
        ]);
    }
}
