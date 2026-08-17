<?php

namespace App\Listeners;

use App\Models\RoleAuditLog;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

class LogRolePermissionChanges
{
    public function handleRoleAttached(RoleAttachedEvent $event): void
    {
        $this->log('role.assigned', $event->model, $event->rolesOrIds);
    }

    public function handleRoleDetached(RoleDetachedEvent $event): void
    {
        $this->log('role.revoked', $event->model, $event->rolesOrIds);
    }

    public function handlePermissionAttached(PermissionAttachedEvent $event): void
    {
        $this->log('permission.granted', $event->model, $event->permissionsOrIds);
    }

    public function handlePermissionDetached(PermissionDetachedEvent $event): void
    {
        $this->log('permission.revoked', $event->model, $event->permissionsOrIds);
    }

    private function log(string $action, Model $model, mixed $items): void
    {
        $changes = $this->normalize($items);

        if ($changes === []) {
            return;
        }

        RoleAuditLog::create([
            'actor_id' => auth()->user()?->id,
            'action' => $action,
            'subject_type' => $model::class,
            'subject_id' => $model->getKey(),
            'changes' => $changes,
        ]);
    }

    /**
     * @return list<string>
     */
    private function normalize(mixed $items): array
    {
        return collect($items)
            ->map(function (mixed $item): string {
                if (is_object($item) && isset($item->name)) {
                    return (string) $item->name;
                }

                return (string) $item;
            })
            ->values()
            ->all();
    }
}
