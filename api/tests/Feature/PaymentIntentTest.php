<?php

namespace Tests\Feature;

use App\Jobs\SubmitChargeToProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentIntentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/payment-intents', [])->assertUnauthorized();
    }

    public function test_requires_idempotency_key(): void
    {
        $this->postJson('/api/payment-intents', ['amount_minor' => 1000, 'currency' => 'USD'])->assertStatus(400);
    }

    public function test_same_key_returns_same_intent_and_charges_once(): void
    {
        $headers = ['Idempotency-Key' => 'key-123'];
        $body = ['amount_minor' => 1000, 'currency' => 'USD'];

        $first = $this->postJson('/api/payment-intents', $body, $headers)->assertCreated();
        $second = $this->postJson('/api/payment-intents', $body, $headers)->assertOk();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertDatabaseCount('payment_intents', 1);
        Queue::assertPushed(SubmitChargeToProvider::class, 1);
    }

    public function test_same_key_with_different_body_is_rejected(): void
    {
        $headers = ['Idempotency-Key' => 'key-xyz'];
        $this->postJson('/api/payment-intents', ['amount_minor' => 1000, 'currency' => 'USD'], $headers)->assertCreated();
        $this->postJson('/api/payment-intents', ['amount_minor' => 9999, 'currency' => 'USD'], $headers)->assertStatus(422);
    }
}
