<?php

namespace Spatie\Fork;

use Closure;
use Socket;

class Process
{
    protected int $pid;

    protected string $name;

    protected int $status;

    protected Socket $socket;

    protected ?Closure $successCallback = null;

    protected int $startTime;

    protected Closure $callable;

    public function __construct(callable $callable)
    {
        $this->callable = Closure::fromCallable($callable);
    }

    public static function fromCallable(callable $callable): self
    {
        return new self($callable);
    }

    public function execute(): string | bool
    {
        return json_encode(($this->callable)());
    }

    public function read(): ?string
    {
        socket_recv($this->socket, $data, 1_000_000, MSG_WAITALL);

        return $data;
    }

    public function onSuccess(callable $callback): Process
    {
        $this->successCallback = $callback;

        return $this;
    }

    public function handleSuccess(): string
    {
        $output = $this->read();

        socket_close($this->socket());

        $this->triggerSuccessCallback();

        return $output;
    }

    public function triggerSuccessCallback(): mixed
    {
        if (! $this->successCallback) {
            return null;
        }

        return call_user_func_array($this->successCallback, [$this]);
    }

    public function setPid(int $pid): Process
    {
        $this->pid = $pid;

        return $this;
    }

    public function pid(): int
    {
        return $this->pid;
    }

    public function setSocket($socket): self
    {
        $this->socket = $socket;

        return $this;
    }

    public function socket(): Socket
    {
        return $this->socket;
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

    public function startTime(): int
    {
        return $this->startTime;
    }

    public function setStartTime($startTime): self
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function didFinishSuccessfully(): bool
    {
        return $this->status === $this->pid;
    }
}
