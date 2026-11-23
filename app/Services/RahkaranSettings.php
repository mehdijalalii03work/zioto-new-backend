<?php

namespace App\Services;

class RahkaranSettings
{
    public function get(string $key): mixed
    {
        return config("rahkaran.{$key}");
    }
}
