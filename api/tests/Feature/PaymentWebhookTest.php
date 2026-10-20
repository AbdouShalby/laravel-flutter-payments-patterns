<?php

namespace Tests\Feature;

use App\Models\PaymentIntent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.webhook_secret' => $this->secret]);
    }

    private function send(array $payload, ?string $signature = null, ?int $ts = null)
    {
        $ts ??= time();
        $body = json_encode($payload);
        $signature ??= hash_hmac('sha256', $ts.'.'.$body, $this->secret);

        return $this->call('POST', '/api/webhooks/payments', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TIMESTAMP' => (string) $ts,
            'HTTP_X_SIGNATURE' => $signature,
        ], $body);
    }

    private function intent(string $status = 'processing'): PaymentIntent
    {
        return PaymentIntent::create([
            'user_id' => User::factory()->create()->id, 'idempotency_key' => 'k', 'request_hash' => str_repeat('a', 64),
            'amount_minor' => 500, 'currency' => 'USD', 'status' => $status,
        ]);
    }

    public function test_rejects_bad_signature(): void
    {
        $this->send(['id' => 'evt_1', 'type' => 'charge.succeeded', 'intent_id' => $this->intent()->id], 'bad')->assertUnauthorized();
    }

    public function test_rejects_stale_timestamp(): void
    {
        $this->send(['id' => 'evt_1', 'type' => 'charge.succeeded', 'intent_id' => $this->intent()->id], null, time() - 3600)->assertUnauthorized();
    }

    public function test_duplicate_event_is_processed_once(): void
    {
        $intent = $this->intent();
        $payload = ['id' => 'evt_1', 'type' => 'charge.succeeded', 'intent_id' => $intent->id];

        $this->send($payload)->assertOk();
        $this->send($payload)->assertOk();

        $this->assertDatabaseCount('payment_events', 1);
        $this->assertSame('succeeded', $intent->fresh()->status);
    }

    public function test_late_failure_cannot_downgrade_success(): void
    {
        $intent = $this->intent('succeeded');
        $this->send(['id' => 'evt_2', 'type' => 'charge.failed', 'intent_id' => $intent->id])->assertOk();
        $this->assertSame('succeeded', $intent->fresh()->status);
    }
}
