<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Próximos eventos');
        $response->assertDontSee('aria-current="date"', false);
        $response->assertViewHas('greeting');
        $response->assertDontSee('Casa Arellano');
    }

    public function test_a_calendar_date_can_be_preselected(): void
    {
        $response = $this->get('/?date=2026-08-06');

        $response->assertOk()
            ->assertSee('Eventos del 6 de agosto')
            ->assertSee('aria-current="date"', false)
            ->assertSee('data-calendar-date="2026-08-06"', false);
    }
}
