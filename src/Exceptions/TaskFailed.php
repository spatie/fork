<?php

namespace Spatie\Fork\Exceptions;

use Exception;
use Throwable;

/**
 * Passed to the catch() callback in place of an exception thrown in a child task that could not be serialized
 * (for example because it holds a closure). It keeps the original exception's class, message, file and line.
 */
class TaskFailed extends Exception
{
    public string $originalClass = '';

    public string $originalTrace = '';

    /**
     * @return array{class: string, message: string, code: int, file: string, line: int, trace: string}
     */
    public static function describe(Throwable $exception): array
    {
        return [
            'class' => $exception::class,
            'message' => $exception->getMessage(),
            'code' => is_int($exception->getCode()) ? $exception->getCode() : 0,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];
    }

    /**
     * @param  array{class: string, message: string, code: int, file: string, line: int, trace: string}  $description
     */
    public static function fromDescription(array $description): self
    {
        $exception = new self("{$description['class']}: {$description['message']}", $description['code']);

        $exception->file = $description['file'];
        $exception->line = $description['line'];
        $exception->originalClass = $description['class'];
        $exception->originalTrace = $description['trace'];

        return $exception;
    }
}
