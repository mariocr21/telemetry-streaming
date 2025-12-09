<?php
namespace App\Logging;

use App\Events\LogEntryCreated;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

class RealtimeLogHandler extends AbstractProcessingHandler
{
    private static bool $dispatching = false;

    protected function write(LogRecord $record): void
    {
        if (self::$dispatching) {
            return; // Evitar recursión
        }

        self::$dispatching = true;
        
        try {
            LogEntryCreated::dispatch($record->toArray());
        } finally {
            self::$dispatching = false;
        }
    }
}