<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('price-board', fn () => true);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
