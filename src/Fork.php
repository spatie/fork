<?php

namespace Spatie\Fork;

use Closure;
use Spatie\Fork\Exceptions\CouldNotManageProcess;

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
        $wrappedCallables = [];

        foreach ($callables as $i => $callable) {
            $process = Process::fromCallable($callable, $i);

            $wrappedCallables[] = $this->runProcess($process);
        }

        return $this->waitFor(...$wrappedCallables);
    }

    protected function runProcess(Process $process): Process
    {
        socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets);

        [$socketToParent, $socketToChild] = $sockets;

        $processId = pcntl_fork();

        if ($this->currentlyInChildProcess($processId)) {
            $this->executingInChildProcess($socketToChild, $socketToParent, $process);
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
                $result = $this->monitorProcess($process);

                if ($result['finished'] === true) {
                    $output[$process->order()] = $result['output'];
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

    protected function monitorProcess(Process $process): array
    {
        $processStatus = pcntl_waitpid($process->pid(), $status, WNOHANG | WUNTRACED);

        $process->setStatus($processStatus);

        if ($process->didFinishSuccessfully()) {
            return ['finished' => true, 'output' => $process->handleSuccess()];
        }

        if ($processStatus !== 0) {
            throw CouldNotManageProcess::make($process);
        }

        return ['finished' => false];
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

        socket_write($socketToParent, $process->execute());

        if ($this->after) {
            ($this->after)();
        }

        socket_close($socketToParent);

        exit;
    }
}
