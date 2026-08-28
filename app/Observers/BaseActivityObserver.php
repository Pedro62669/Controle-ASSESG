<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\LogAction;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Base dos observers de auditoria: traduz eventos Eloquent em registros
 * de system_logs, filtrando atributos que não devem ser persistidos.
 */
abstract class BaseActivityObserver
{
    public function __construct(protected readonly ActivityLogger $logger) {}

    public function created(Model $model): void
    {
        $this->logger->log(
            model: $model,
            action: LogAction::Created,
            description: $this->describe($model, LogAction::Created),
            newValues: $this->sanitize($model->getAttributes()),
        );
    }

    public function updated(Model $model): void
    {
        $changes = $this->sanitize($model->getChanges());

        // getChanges() ainda inclui updated_at; sem outros campos não houve
        // alteração de negócio e o log seria puro ruído.
        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        $original = array_intersect_key(
            $this->sanitize($model->getRawOriginal()),
            $changes,
        );

        $this->logger->log(
            model: $model,
            action: LogAction::Updated,
            description: $this->describe($model, LogAction::Updated),
            oldValues: $original,
            newValues: $changes,
        );
    }

    public function deleted(Model $model): void
    {
        $this->logger->log(
            model: $model,
            action: LogAction::Deleted,
            description: $this->describe($model, LogAction::Deleted),
            oldValues: $this->sanitize($model->getRawOriginal()),
        );
    }

    public function restored(Model $model): void
    {
        $this->logger->log(
            model: $model,
            action: LogAction::Restored,
            description: $this->describe($model, LogAction::Restored),
            newValues: $this->sanitize($model->getAttributes()),
        );
    }

    /**
     * Texto legível exibido na tela de logs.
     */
    abstract protected function describe(Model $model, LogAction $action): string;

    /**
     * Atributos que nunca podem chegar à tabela de logs.
     *
     * @return list<string>
     */
    protected function hidden(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function sanitize(array $attributes): array
    {
        return array_diff_key($attributes, array_flip($this->hidden()));
    }
}
