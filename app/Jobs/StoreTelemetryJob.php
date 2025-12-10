<?php

namespace App\Jobs;

use App\Services\TelemetryIngestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StoreTelemetryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Payload completo de la trama de telemetría.
     *
     * @var array
     */
    public array $payload;

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
        // Puedes opcionalmente ajustar la prioridad/queue aquí:
        $this->onQueue('telemetry');
    }

    /**
     * Execute the job.
     */
    public function handle(TelemetryIngestService $telemetryIngestService): void
    {
        // Aquí NO hacemos lógica, solo delegamos al service.
        $telemetryIngestService->ingestFromJob($this->payload);
    }
}
