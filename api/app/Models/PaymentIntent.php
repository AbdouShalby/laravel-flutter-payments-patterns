<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PaymentIntent extends Model
{
    use HasUuids;

    public const ORDER = ['pending' => 0, 'processing' => 1, 'succeeded' => 2, 'failed' => 2];
    public const FINAL = ['succeeded', 'failed'];

    protected $fillable = ['user_id', 'idempotency_key', 'request_hash', 'amount_minor', 'currency', 'status', 'provider_reference'];

    /** Forward-only transition. Returns false when the move is ignored. */
    public function transitionTo(string $next): bool
    {
        if (in_array($this->status, self::FINAL, true)) {
            return false;
        }
        if ((self::ORDER[$next] ?? -1) <= self::ORDER[$this->status]) {
            return false;
        }
        $this->status = $next;
        return $this->save();
    }
}
