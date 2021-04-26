<?php

namespace Spatie\Fork;

use Closure;
use Socket;
use Spatie\Fork\Exceptions\CouldNotManageTask;

class Task
{
    public const BUFFER_LENGTH = 1024;

    protected string $name;

    private int $order;

    protected int $pid;

    protected int $status;

    protected Socket $socket;

    protected ?Closure $successCallback = null;

    protected int $startTime;

    protected Closure $callable;

    public static function fromCallable(callable $callable, int $order): self
    {
        return new self($callable, $order);
    }

    public function __construct(callable $callable, int $order)
    {
        $this->callable = Closure::fromCallable($callable);

        $this->order = $order;
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

    public function socket(): Socket
    {
        return $this->socket;
    }

    public function setSocket($socket): self
    {
        $this->socket = $socket;

        return $this;
    }

    public function startTime(): int
    {
        return $this->startTime;
    }

    public function setStartTime($startTime): self
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function execute(): string | bool
    {
        $output = ($this->callable)();

        return json_encode($output);
    }

    public function onSuccess(callable $callback): self
    {
        $this->successCallback = $callback;

        return $this;
    }

    public function output(): ?string
    {
        socket_recv($this->socket, $output, self::BUFFER_LENGTH, MSG_WAITALL);

        socket_close($this->socket());

        $this->triggerSuccessCallback();

        return $output;
    }

    public function isFinished(): bool
    {
        $status = pcntl_waitpid($this->pid(), $status, WNOHANG | WUNTRACED);

        if ($status === $this->pid) {
            return true;
        }

        if ($status !== 0) {
            throw CouldNotManageTask::make($this);
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
