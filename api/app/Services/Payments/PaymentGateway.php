<?php

namespace App\Services\Payments;

/** Provider abstraction. A fake is bound in tests; a real adapter wraps any PSP SDK. */
interface PaymentGateway
{
    /** Must forward $idempotencyKey to the provider so provider-side retries are safe too. */
    public function createCharge(string $intentId, int $amountMinor, string $currency, string $idempotencyKey): string;
}
