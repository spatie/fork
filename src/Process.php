<?php

namespace Spatie\Fork;

use Closure;
use Socket;
use Spatie\Fork\Exceptions\CouldNotManageProcess;

class Process
{
    public const BUFFER_LENGHT = 1024;

    private int $order;

    protected int $pid;

    protected string $name;

    protected int $status;

    protected Socket $socket;

    protected ?Closure $successCallback = null;

    protected int $startTime;

    protected Closure $callable;

    public function __construct(callable $callable, int $order)
    {
        $this->callable = Closure::fromCallable($callable);
        $this->order = $order;
    }

    public static function fromCallable(callable $callable, int $order): self
    {
        return new self($callable, $order);
    }

    public function execute(): string | bool
    {
        return json_encode(($this->callable)());
    }

    public function onSuccess(callable $callback): Process
    {
        $this->successCallback = $callback;

        return $this;
    }

    public function output(): ?string
    {
        socket_recv($this->socket, $output, self::BUFFER_LENGHT, MSG_WAITALL);

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

    public function isFinished(): bool
    {
        $status = pcntl_waitpid($this->pid(), $status, WNOHANG | WUNTRACED);

        if ($status === $this->pid) {
            return true;
        }

        if ($status !== 0) {
            throw CouldNotManageProcess::make($this);
        }

        return false;
    }

    public function order(): int
    {
        return $this->order;
    }
}
