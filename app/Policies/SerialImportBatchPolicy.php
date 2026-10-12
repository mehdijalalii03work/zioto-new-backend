<?php

namespace App\Policies;

use App\Models\User;

class SerialImportBatchPolicy extends AdminPolicy
{
    public static function entity(): string
    {
        return 'serial';
    }

    /**
     * Import history is append-only: batches are never edited or deleted
     * through the panel, so every write ability stays denied.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, mixed $model = null): bool
    {
        return false;
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return false;
    }
}
