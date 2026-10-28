<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;

class ClientTransactionWriter
{
    /** A client ID identifies one immutable submission, even across API endpoints. */
    public function createOrMatch(User $actor, string $clientId, array $values): Transaction
    {
        $existing = Transaction::withTrashed()->where('client_id', $clientId)
            ->lockForUpdate()->first();

        if ($existing) {
            abort_unless((int) $existing->business_id === (int) $actor->business_id, 409,
                'This transaction identifier cannot be reused.');
            abort_if($actor->isStaff() && (int) $existing->user_id !== (int) $actor->id, 403,
                'Staff can only retry transactions they recorded.');
            abort_if($existing->trashed() || $existing->order_id || $existing->reversal_of_id
                || in_array($existing->entry_method, ['order_system', 'order_reversal'], true), 409,
                'This transaction cannot be submitted again.');

            $same = (int) round((float) $existing->amount * 100) === (int) round((float) $values['amount'] * 100)
                && $existing->type === $values['type']
                && $existing->category === $values['category']
                && $existing->source === ($values['source'] ?? null)
                && $existing->transaction_date->toDateString() === $values['transaction_date']
                && $existing->description === ($values['description'] ?? null);

            if (array_key_exists('entry_method', $values)) {
                $same = $same && $existing->entry_method === $values['entry_method'];
            }
            if (array_key_exists('metadata', $values)) {
                $same = $same && ($existing->metadata ?? []) === ($values['metadata'] ?? []);
            }

            abort_unless($same, 409, 'This transaction identifier was already used with different details.');

            return $existing;
        }

        return Transaction::create($values + [
            'business_id' => $actor->business_id,
            'user_id' => $actor->id,
            'client_id' => $clientId,
        ]);
    }
}
