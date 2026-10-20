<?php

namespace App\Http\Controllers;

use App\Models\PaymentIntent;
use App\Services\Payments\CreatePaymentIntent;
use Illuminate\Http\Request;

class PaymentIntentController extends Controller
{
    public function store(Request $request, CreatePaymentIntent $create)
    {
        $key = (string) $request->header('Idempotency-Key', '');
        abort_if($key === '' || strlen($key) > 100, 400, 'Idempotency-Key header required');

        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        [$intent, $created] = $create->handle($request->user()->id, $key, $data['amount_minor'], $data['currency']);

        return response()->json($this->present($intent), $created ? 201 : 200);
    }

    public function show(Request $request, PaymentIntent $intent)
    {
        abort_unless($intent->user_id === $request->user()->id, 404);
        return response()->json($this->present($intent));
    }

    private function present(PaymentIntent $i): array
    {
        return ['id' => $i->id, 'status' => $i->status, 'amount_minor' => $i->amount_minor, 'currency' => $i->currency];
    }
}
