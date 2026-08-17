<?php

namespace App\Observers;

use App\Models\RoleAuditLog;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Contracts\Role;

class RolePermissionAuditObserver
{
    public function created(Model $model): void
    {
        $this->log('created', $model);
    }

    public function updated(Model $model): void
    {
        $this->log('updated', $model, $model->getChanges());
    }

    public function deleted(Model $model): void
    {
        $this->log('deleted', $model);
    }

    private function log(string $action, Model $model, array $changes = []): void
    {
        $prefix = $model instanceof Role ? 'role' : ($model instanceof Permission ? 'permission' : 'entity');

        RoleAuditLog::create([
            'actor_id' => auth()->user()?->id,
            'action' => $prefix.'.'.$action,
            'subject_type' => $model::class,
            'subject_id' => $model->getKey(),
            'changes' => $changes,
        ]);
    }
}
