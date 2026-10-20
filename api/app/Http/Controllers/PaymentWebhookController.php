<?php

namespace App\Http\Controllers;

use App\Models\PaymentEvent;
use App\Models\PaymentIntent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentWebhookController extends Controller
{
    private const STATUS_BY_TYPE = [
        'charge.succeeded' => 'succeeded',
        'charge.failed' => 'failed',
    ];

    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'string'],
            'type' => ['required', 'string'],
            'intent_id' => ['required', 'uuid'],
        ]);

        try {
            DB::transaction(function () use ($data, $request) {
                PaymentEvent::create([
                    'provider_event_id' => $data['id'],
                    'payment_intent_id' => $data['intent_id'],
                    'type' => $data['type'],
                    'payload' => $request->all(),
                ]);

                $next = self::STATUS_BY_TYPE[$data['type']] ?? null;
                if ($next) {
                    PaymentIntent::whereKey($data['intent_id'])->lockForUpdate()->first()?->transitionTo($next);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Duplicate delivery: already processed.
        }

        return response()->json(['received' => true]);
    }
}
