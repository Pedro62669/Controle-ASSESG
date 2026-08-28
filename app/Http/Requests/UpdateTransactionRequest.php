<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Transaction;

class UpdateTransactionRequest extends StoreTransactionRequest
{
    /**
     * Somente o autor da movimentação ou o administrador principal podem editá-la.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $transaction = $this->route('transaction');

        if ($user === null || $user->is_active !== true) {
            return false;
        }

        if (! $transaction instanceof Transaction) {
            return false;
        }

        return $user->isMainAdmin() || $transaction->user_id === $user->getKey();
    }
}
