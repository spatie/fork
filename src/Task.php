<?php

namespace Spatie\Fork;

use Closure;
use Spatie\Fork\Exceptions\CouldNotManageTask;
use Spatie\Fork\Exceptions\TaskFailed;
use Throwable;

class Task
{
    protected const SERIALIZATION_TOKEN = '[[serialized::';

    protected string $name;

    protected int $pid;

    protected int $status;

    protected Connection $connection;

    protected ?Closure $successCallback = null;

    protected int $startTime;

    protected Closure $callable;

    protected string $output = '';

    protected bool $catchExceptions = false;

    protected ?Throwable $exception = null;

    public static function fromCallable(callable $callable, int $order): self
    {
        return new self($callable, $order);
    }

    public function __construct(callable $callable, protected int $order)
    {
        $this->callable = $callable(...);
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function order(): int
    {
        return $this->order;
    }

    public function pid(): int
    {
        return $this->pid;
    }

    public function setPid(int $pid): self
    {
        $this->pid = $pid;

        return $this;
    }

    public function setConnection(Connection $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    public function closeConnection(): void
    {
        $this->connection->close();
    }

    public function startTime(): int
    {
        return $this->startTime;
    }

    public function setStartTime(int $startTime): self
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function setCatchExceptions(bool $catchExceptions): self
    {
        $this->catchExceptions = $catchExceptions;

        return $this;
    }

    public function execute(): string
    {
        try {
            $output = ($this->callable)();

            if ($this->catchExceptions) {
                return serialize(['output' => $output]);
            }

            if (is_string($output)) {
                return $output;
            }

            return self::SERIALIZATION_TOKEN.serialize($output);
        } catch (Throwable $exception) {
            if (! $this->catchExceptions) {
                throw $exception;
            }

            return $this->serializeException($exception);
        }
    }

    public function output(): mixed
    {
        foreach ($this->connection->read() as $output) {
            $this->output .= $output;
        }

        $this->connection->close();

        $this->triggerSuccessCallback();

        $output = $this->output;

        if ($this->catchExceptions && $output !== '') {
            $payload = unserialize($output);

            if (array_key_exists('description', $payload)) {
                $this->exception = $this->unserializeException($payload);

                return null;
            }

            return $payload['output'];
        }

        if (str_starts_with($output, self::SERIALIZATION_TOKEN)) {
            $output = unserialize(
                substr($output, strlen(self::SERIALIZATION_TOKEN))
            );
        }

        return $output;
    }

    /**
     * The exception the task threw, when exceptions are caught (see Fork::catch()).
     */
    public function exception(): ?Throwable
    {
        return $this->exception;
    }

    /**
     * The exception itself is sent when it can be serialized, and a description of it when it can't (for example
     * because it holds a closure), so the parent always gets to know what went wrong.
     */
    protected function serializeException(Throwable $exception): string
    {
        $description = TaskFailed::describe($exception);

        try {
            $serialized = serialize($exception);
        } catch (Throwable) {
            $serialized = null;
        }

        return serialize(['exception' => $serialized, 'description' => $description]);
    }

    /**
     * @param  array{exception: ?string, description: array{class: string, message: string, code: int, file: string, line: int, trace: string}}  $payload
     */
    protected function unserializeException(array $payload): Throwable
    {
        if ($payload['exception'] !== null) {
            try {
                return unserialize($payload['exception']);
            } catch (Throwable) {
                // Custom restoration hooks may fail only in the parent process.
            }
        }

        return TaskFailed::fromDescription($payload['description']);
    }

    public function onSuccess(callable $callback): self
    {
        $this->successCallback = $callback;

        return $this;
    }

    public function isFinished(): bool
    {
        $this->output .= $this->connection->read()->current();

        if (pcntl_waitpid($this->pid(), $status, WNOHANG) === $this->pid) {

            if (pcntl_wexitstatus($status) !== 0) {
                throw CouldNotManageTask::make($this);
            }

            return true;
        }

        return false;
    }

    public function triggerSuccessCallback(): mixed
    {
        if (! $this->successCallback) {
            return null;
        }

        return call_user_func_array($this->successCallback, [$this]);
    }
}
