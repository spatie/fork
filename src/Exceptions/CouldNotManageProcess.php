<?php

namespace Spatie\Fork\Exceptions;

use Exception;
use Spatie\Fork\Process;

class CouldNotManageProcess extends Exception
{
    public static function make(Process $process): self
    {
        return new self("Could not reliably manage process {$process->pid()}");
    }
}
