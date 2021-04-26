<?php

namespace Spatie\Fork\Exceptions;

use Exception;
use Spatie\Fork\Task;

class CouldNotManageProcess extends Exception
{
    public static function make(Task $process): self
    {
        return new self("Could not reliably manage process {$process->pid()}");
    }
}
