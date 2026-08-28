<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LogAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'user_id',
    'user_name',
    'action',
    'loggable_type',
    'loggable_id',
    'description',
    'old_values',
    'new_values',
    'ip_address',
    'user_agent',
])]
class SystemLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => LogAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Nome legível do recurso auditado (ex.: "Transação").
     */
    public function loggableLabel(): string
    {
        return match ($this->loggable_type) {
            Transaction::class => 'Transação',
            User::class => 'Usuário',
            default => class_basename($this->loggable_type),
        };
    }

    /**
     * Campos efetivamente alterados, pareando valor antigo e novo.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function changes(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        $keys = array_unique([...array_keys($old), ...array_keys($new)]);

        $changes = [];

        foreach ($keys as $key) {
            $changes[$key] = [
                'old' => $old[$key] ?? null,
                'new' => $new[$key] ?? null,
            ];
        }

        return $changes;
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term): void {
            $query->where('description', 'like', "%{$term}%")
                ->orWhere('user_name', 'like', "%{$term}%");
        });
    }
}
