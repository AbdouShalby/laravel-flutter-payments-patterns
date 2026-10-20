<?php

namespace App\Services\Payments;

use App\Jobs\SubmitChargeToProvider;
use App\Models\PaymentIntent;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class CreatePaymentIntent
{
    /** @return array{0: PaymentIntent, 1: bool} intent and whether it was newly created */
    public function handle(int $userId, string $key, int $amountMinor, string $currency): array
    {
        $hash = hash('sha256', $amountMinor.'|'.strtoupper($currency));

        $existing = PaymentIntent::where('user_id', $userId)->where('idempotency_key', $key)->first();
        if ($existing) {
            return [$this->assertSameRequest($existing, $hash), false];
        }

        try {
            $intent = PaymentIntent::create([
                'user_id' => $userId,
                'idempotency_key' => $key,
                'request_hash' => $hash,
                'amount_minor' => $amountMinor,
                'currency' => strtoupper($currency),
            ]);
        } catch (QueryException $e) {
            // Lost a race with a concurrent request using the same key: the unique index decided.
            $winner = PaymentIntent::where('user_id', $userId)->where('idempotency_key', $key)->firstOrFail();
            return [$this->assertSameRequest($winner, $hash), false];
        }

        SubmitChargeToProvider::dispatch($intent->id);

        return [$intent, true];
    }

    private function assertSameRequest(PaymentIntent $intent, string $hash): PaymentIntent
    {
        if (! hash_equals($intent->request_hash, $hash)) {
            throw ValidationException::withMessages(['idempotency_key' => 'Key already used with a different request.']);
        }
        return $intent;
    }
}
