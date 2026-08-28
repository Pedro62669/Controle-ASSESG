<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\LogAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserObserver extends BaseActivityObserver
{
    /**
     * @param  User  $model
     */
    protected function describe(Model $model, LogAction $action): string
    {
        $verb = match ($action) {
            LogAction::Created => 'Cadastrou',
            LogAction::Updated => 'Alterou',
            LogAction::Deleted => 'Removeu',
            LogAction::Restored => 'Reativou',
        };

        return sprintf('%s o usuário %s (%s)', $verb, $model->name, $model->email);
    }

    /**
     * Credenciais jamais são gravadas na auditoria — nem o hash.
     *
     * @return list<string>
     */
    protected function hidden(): array
    {
        return ['password', 'remember_token', 'updated_at'];
    }
}
