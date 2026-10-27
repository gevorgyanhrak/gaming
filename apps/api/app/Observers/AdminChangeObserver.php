<?php

namespace App\Observers;

use App\Platform\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Audits changes administrators make to configuration models (games, modes, bots, pages).
 * Changes without an admin actor (seeders, the scheduler) are not recorded.
 */
class AdminChangeObserver
{
    private const IGNORED = ['created_at', 'updated_at'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function created(Model $model): void
    {
        $this->record($model, 'created', array_map(
            fn ($value) => ['old' => null, 'new' => $value],
            array_diff_key($model->getAttributes(), array_flip(self::IGNORED)),
        ));
    }

    public function updated(Model $model): void
    {
        $changes = [];
        foreach (array_diff_key($model->getChanges(), array_flip(self::IGNORED)) as $attribute => $value) {
            $changes[$attribute] = ['old' => $model->getPrevious()[$attribute] ?? null, 'new' => $value];
        }

        if ($changes !== []) {
            $this->record($model, 'updated', $changes);
        }
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', []);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function record(Model $model, string $event, array $changes): void
    {
        if ($this->audit->actor() === null) {
            return;
        }

        $this->audit->record(str(class_basename($model))->snake()->append('.', $event)->toString(), $model, $changes);
    }
}
