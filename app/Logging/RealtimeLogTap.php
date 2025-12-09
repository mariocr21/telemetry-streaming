<?php
namespace App\Logging;

class RealtimeLogTap
{
    public function __invoke($logger)
    {
        $logger->pushHandler(new RealtimeLogHandler());
    }
}