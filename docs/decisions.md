# Decisions
1. **Idempotency key scoped to user.** Unique index on (user_id, idempotency_key). The request hash is stored; same key with a different body returns 422 instead of silently reusing.
2. **Database is the authority.** Uniqueness constraints, not in-memory checks, decide duplicates, so it is safe across multiple API instances.
3. **Webhooks verified before parsing.** Raw body + timestamp signed with HMAC-SHA256; `hash_equals` avoids timing leaks; old timestamps rejected to stop replays.
4. **Events stored once.** `payment_events.provider_event_id` is unique; a duplicate delivery returns 200 without side effects.
5. **Forward-only state machine.** Out-of-order events cannot downgrade a succeeded payment.
6. **Retries only where safe.** Provider calls are retried with backoff and pass the same idempotency key to the provider; the client retries with the same key too.
