<?php

namespace App\Console\Commands;

use App\Jobs\StoreTelemetryJob;
use App\Services\TelemetryIngestService;
use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;

class ListenTelemetryMqtt extends Command
{
    protected $signature = 'mqtt:telemetry-listen';
    protected $description = 'Escucha telemetría de vehículos vía MQTT y la procesa con Laravel';

    public function handle(TelemetryIngestService $telemetryIngestService)
    {
        $server   = config('mqtt.host');
        $port     = config('mqtt.port', 1883);
        $clientId = config('mqtt.client_id', 'laravel_telemetry_' . uniqid());
        $username = config('mqtt.username');
        $password = config('mqtt.password');
        $topic    = config('mqtt.telemetry_topic', 'vehicles/telemetry');

        $connectionSettings = (new ConnectionSettings)
            ->setUsername($username)
            ->setPassword($password)
            ->setKeepAliveInterval(60)
            ->setUseTls(config('mqtt.tls', false));

        $client = new MqttClient($server, $port, $clientId);

        $client->connect($connectionSettings, true);

        $this->info("✅ Conectado a MQTT {$server}:{$port}, escuchando en [{$topic}]...");

        $client->subscribe($topic, function (string $topic, string $message) {
            try {
                $data = json_decode($message, true);

                if (!is_array($data)) {
                    Log::warning('MQTT telemetry: invalid JSON', [
                        'topic'   => $topic,
                        'message' => $message,
                    ]);
                    return;
                }

                // En este punto NO hacemos trabajo pesado, solo encolamos el job.
                // El job decidirá según "d" si guarda o no.
                StoreTelemetryJob::dispatch($data);
            } catch (\Throwable $e) {
                Log::error('MQTT telemetry: error processing message', [
                    'topic'   => $topic,
                    'message' => $message,
                    'error'   => $e->getMessage(),
                ]);
            }
        }, 1);

        // Bucle principal
        $client->loop(true);
    }
}
