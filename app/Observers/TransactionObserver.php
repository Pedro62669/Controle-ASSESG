<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\LogAction;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;

class TransactionObserver extends BaseActivityObserver
{
    /**
     * @param  Transaction  $model
     */
    protected function describe(Model $model, LogAction $action): string
    {
        $verb = match ($action) {
            LogAction::Created => 'registrou',
            LogAction::Updated => 'alterou',
            LogAction::Deleted => 'excluiu',
            LogAction::Restored => 'restaurou',
        };

        return sprintf(
            '%s a %s de %s com data de %s',
            ucfirst($verb),
            mb_strtolower($model->type->label()),
            $model->formatted_amount,
            $model->transaction_date?->format('d/m/Y') ?? 'data não informada',
        );
    }

    /**
     * @return list<string>
     */
    protected function hidden(): array
    {
        return ['updated_at'];
    }
}
