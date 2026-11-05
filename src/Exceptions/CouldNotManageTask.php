<?php

namespace Spatie\Fork\Exceptions;

use Exception;
use Spatie\Fork\Task;

class CouldNotManageTask extends Exception
{
    public static function make(Task $task): self
    {
        return new self("Could not reliably manage task that uses process id {$task->pid()}");
    }

    public static function forkFailed(string $reason): self
    {
        return new self("Could not fork a child process for the task: {$reason}");
    }
}
