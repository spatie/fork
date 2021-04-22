<?php

namespace Spatie\Fork;

use Closure;

class Process
{
    protected $pid;

    protected $name;

    protected $socket;

    protected $successCallback;

    protected $startTime;

    protected $maxRunTime = 300;

    private Closure $callable;

    public function __construct(callable $callable)
    {
        $this->callable = Closure::fromCallable($callable);
    }

    public static function fromCallable(callable $callable): self
    {
        return new self($callable);
    }

    public function execute(): mixed
    {
        return ($this->callable)();
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

    public function triggerSuccess()
    {
        if (! $this->successCallback) {
            return null;
        }

        return call_user_func_array($this->successCallback, [$this]);
    }

    public function setPid($pid): Process
    {
        $this->pid = $pid;

        return $this;
    }

    public function getPid()
    {
        return $this->pid;
    }

    public function setSocket($socket): Process
    {
        $this->socket = $socket;

        return $this;
    }

    public function getSocket()
    {
        return $this->socket;
    }

    public function setName(string $name): Process
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getStartTime()
    {
        return $this->startTime;
    }

    public function setMaxRunTime(int $maxRunTime): Process
    {
        $this->maxRunTime = $maxRunTime;

        return $this;
    }

    public function getMaxRunTime(): int
    {
        return $this->maxRunTime;
    }
}
