<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PriceBoardUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly array $prices,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('price-board'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'prices.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'prices' => $this->prices,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
