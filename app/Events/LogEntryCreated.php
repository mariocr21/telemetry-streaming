<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LogEntryCreated implements ShouldBroadcastNow  // ← Agregar interfaz
{
    use Dispatchable, SerializesModels;

    public array $logData;

    public $broadcastQueue = 'logs';


    public function __construct(array $logData)
    {
        $this->logData = $logData;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('log-monitoring');
    }

    // Qué datos enviar al frontend
    public function broadcastWith(): array
    {
        return [
            'message' => $this->logData['message'] ?? '',
            'level' => $this->logData['level_name'] ?? $this->logData['level'] ?? 'INFO',
            'context' => $this->logData['context'] ?? [],
            'datetime' => $this->logData['datetime'] ?? now()->toIso8601String(),
        ];
    }
}