<?php

namespace App\Jobs;

use App\Models\PaymentIntent;
use App\Services\Payments\PaymentGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SubmitChargeToProvider implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public function __construct(public string $intentId) {}

    /** Exponential backoff in seconds between attempts. */
    public function backoff(): array
    {
        return [2, 4, 8, 16];
    }

    public function handle(PaymentGateway $gateway): void
    {
        $intent = PaymentIntent::findOrFail($this->intentId);
        if ($intent->status !== 'pending') {
            return; // already submitted or finished
        }

        $ref = $gateway->createCharge($intent->id, $intent->amount_minor, $intent->currency, 'intent-'.$intent->id);

        $intent->provider_reference = $ref;
        $intent->transitionTo('processing');
    }

    public function failed(\Throwable $e): void
    {
        PaymentIntent::find($this->intentId)?->transitionTo('failed');
    }
}
