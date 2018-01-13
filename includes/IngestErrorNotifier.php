<?php

namespace Monolog\Handler;

/**
 * Simple wrapper handler to trigger a notification that Monolog has logged an ERROR.
 */
class IngestErrorNotifier extends HandlerWrapper
{
    public function handle(array $record)
    {
        if (isset($record->level) && $record->level >= 400) {
            return true;
        }
    }
}
