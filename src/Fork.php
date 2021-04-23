<?php

namespace Spatie\Fork;

use Closure;

class Fork
{
    protected ?Closure $before = null;

    protected ?Closure $after = null;

    public static function new(): self
    {
        return new self();
    }

    public function before(callable $before): self
    {
        $this->before = $before;

        return $this;
    }

    public function after(callable $after): self
    {
        $this->after = $after;

        return $this;
    }

    public function run(callable ...$callables): array
    {
        $processes = [];

        foreach ($callables as $order => $callable) {
            $process = Process::fromCallable($callable, $order);

            $processes[] = $this->forkAndStartChildProcess($process);
        }

        return $this->waitFor(...$processes);
    }

    protected function forkAndStartChildProcess(Process $process): Process
    {
        socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets);

        [$socketToParent, $socketToChild] = $sockets;

        $processId = pcntl_fork();

        if ($this->currentlyInChildProcess($processId)) {
            $this->executingInChildProcess($socketToChild, $socketToParent, $process);

            exit;
        }

        socket_close($socketToParent);

        return $process
            ->setStartTime(time())
            ->setPid($processId)
            ->setSocket($socketToChild);
    }

    protected function waitFor(Process ...$runningProcesses): array
    {
        $output = [];

        while (count($runningProcesses)) {
            foreach ($runningProcesses as $key => $process) {
                if ($process->isFinished()) {
                    $output[$process->order()] = $process->output();

                    unset($runningProcesses[$key]);
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

    protected function executingInChildProcess(
        mixed $socketToChild,
        mixed $socketToParent,
        Process $process
    ): void {
        socket_close($socketToChild);

        if ($this->before) {
            ($this->before)();
        }

        $output = $process->execute();

        if (is_string($output) && strlen($output) > Process::BUFFER_LENGTH) {
            $output = substr($output, 0, Process::BUFFER_LENGTH);
        }

        socket_write($socketToParent, $output);

        if ($this->after) {
            ($this->after)();
        }

        socket_close($socketToParent);
    }
}
