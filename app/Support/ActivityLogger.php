<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\LogAction;
use App\Models\SystemLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Ponto único de gravação da trilha de auditoria (tabela system_logs).
 *
 * Isolado dos observers para que qualquer fluxo — inclusive ações que não
 * disparam eventos Eloquent, como um login — possa registrar auditoria.
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function log(
        Model $model,
        LogAction $action,
        string $description,
        array $oldValues = [],
        array $newValues = [],
    ): SystemLog {
        $user = Auth::user();

        return SystemLog::query()->create([
            'user_id' => $user?->getAuthIdentifier(),
            // Congela o nome no momento do fato: o log precisa continuar
            // legível mesmo que o usuário seja renomeado ou removido depois.
            'user_name' => $user?->getAttribute('name') ?? 'Sistema',
            'action' => $action,
            'loggable_type' => $model::class,
            'loggable_id' => $model->getKey(),
            'description' => $description,
            'old_values' => $oldValues === [] ? null : $oldValues,
            'new_values' => $newValues === [] ? null : $newValues,
            'ip_address' => $this->currentIp(),
            'user_agent' => $this->currentUserAgent(),
        ]);
    }

    private function currentIp(): ?string
    {
        return app()->runningInConsole() ? null : request()->ip();
    }

    private function currentUserAgent(): ?string
    {
        return app()->runningInConsole() ? null : request()->userAgent();
    }
}
