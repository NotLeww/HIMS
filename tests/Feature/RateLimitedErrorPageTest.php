<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RateLimitedErrorPageTest extends TestCase
{
    public function test_throttled_html_request_uses_the_custom_hims_error_screen(): void
    {
        Route::get('/_test/rate-limited', fn (): string => 'Request accepted.')
            ->middleware('throttle:1,1,test-rate-limit:');

        $this->get('/_test/rate-limited')->assertOk();

        $this->get('/_test/rate-limited')
            ->assertStatus(429)
            ->assertSee('<title>429 | Too Many Requests &middot; HIMS</title>', false)
            ->assertSee(asset('img/hims-logo.png'), false)
            ->assertSee('Hospital Information Management System')
            ->assertSee('Error 429:', false)
            ->assertSee('Too Many Requests')
            ->assertSee('Please wait a minute before trying again.')
            ->assertSee('Return to Previous Page')
            ->assertSee('temporary limit helps protect account access');
    }

    public function test_throttled_json_request_keeps_laravels_json_error_response(): void
    {
        Route::get('/_test/json-rate-limited', fn (): array => ['accepted' => true])
            ->middleware('throttle:1,1,test-json-rate-limit:');

        $this->getJson('/_test/json-rate-limited')->assertOk();

        $this->getJson('/_test/json-rate-limited')
            ->assertStatus(429)
            ->assertJsonStructure(['message'])
            ->assertDontSee('Hospital Information Management System');
    }
}
