<?php

namespace Spatie\Fork;

use Closure;
use Socket;

class Fork
{
    protected ?Closure $toExecuteBeforeInChildProcess = null;
    protected ?Closure $toExecuteBeforeInParentProcess = null;

    protected ?Closure $toExecuteAfterInChildProcess = null;
    protected ?Closure $toExecuteAfterInParentProcess = null;

    public static function new(): self
    {
        return new self();
    }

    public function before(callable $child = null, callable $parent = null): self
    {
        $this->toExecuteBeforeInChildProcess = $child;
        $this->toExecuteBeforeInParentProcess = $parent;

        return $this;
    }

    public function after(callable $child = null, callable $parent = null): self
    {
        $this->toExecuteAfterInChildProcess = $child;
        $this->toExecuteAfterInParentProcess = $parent;

        return $this;
    }

    public function run(callable ...$callables): array
    {
        $processes = [];

        foreach ($callables as $order => $callable) {
            if ($this->toExecuteBeforeInParentProcess) {
                ($this->toExecuteBeforeInParentProcess)();
            }

            $process = Task::fromCallable($callable, $order);

            $processes[] = $this->forkForProcess($process);
        }

        return $this->waitFor(...$processes);
    }

    protected function forkForProcess(Task $process): Task
    {
        socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets);

        [$socketToParent, $socketToChild] = $sockets;

        $processId = pcntl_fork();

        if ($this->currentlyInChildProcess($processId)) {
            socket_close($socketToChild);

            $this->executeInChildProcess($process, $socketToParent);

            exit;
        }

        socket_close($socketToParent);

        return $process
            ->setStartTime(time())
            ->setPid($processId)
            ->setSocket($socketToChild);
    }

    protected function waitFor(Task ...$runningProcesses): array
    {
        $output = [];

        while (count($runningProcesses)) {
            foreach ($runningProcesses as $key => $process) {
                if ($process->isFinished()) {
                    $output[$process->order()] = $process->output();

                    unset($runningProcesses[$key]);

                    if ($this->toExecuteAfterInParentProcess) {
                        ($this->toExecuteAfterInParentProcess)();
                    }
                }
            }

            if (! count($runningProcesses)) {
                break;
            }

            usleep(1_000);
        }

        return $output;
    }

    protected function currentlyInChildProcess(int $pid): bool
    {
        return $pid === 0;
    }

    protected function executeInChildProcess(
        Task $process,
        Socket $socketToParent,
    ): void {
        if ($this->toExecuteBeforeInChildProcess) {
            ($this->toExecuteBeforeInChildProcess)();
        }

        $output = $process->execute();

        if (is_string($output) && strlen($output) > Task::BUFFER_LENGTH) {
            $output = substr($output, 0, Task::BUFFER_LENGTH);
        }

        socket_write($socketToParent, $output);

        if ($this->toExecuteAfterInChildProcess) {
            ($this->toExecuteAfterInChildProcess)();
        }

        socket_close($socketToParent);
    }
}
