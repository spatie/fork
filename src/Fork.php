<?php

namespace Spatie\Fork;

use Closure;

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
        [$socketToParent, $socketToChild] = Connection::createPair();

        $processId = pcntl_fork();

        if ($this->currentlyInChildProcess($processId)) {
            $socketToChild->close();

            $this->executeInChildProcess($process, $socketToParent);

            exit;
        }

        $socketToParent->close();

        return $process
            ->setStartTime(time())
            ->setPid($processId)
            ->setConnection($socketToChild);
    }

    protected function waitFor(Task ...$runningProcesses): array
    {
        $output = [];

        while (count($runningProcesses)) {
            foreach ($runningProcesses as $key => $process) {
                $process->read();

                if ($process->isFinished()) {
                    $processOutput = $process->output();

                    $output[$process->order()] = $processOutput;

                    unset($runningProcesses[$key]);

                    if ($this->toExecuteAfterInParentProcess) {
                        ($this->toExecuteAfterInParentProcess)($processOutput);
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
        Connection $socketToParent,
    ): void {
        if ($this->toExecuteBeforeInChildProcess) {
            ($this->toExecuteBeforeInChildProcess)();
        }

        $output = $process->execute();

        $socketToParent->write($output);

        if ($this->toExecuteAfterInChildProcess) {
            ($this->toExecuteAfterInChildProcess)($output);
        }

        $socketToParent->close();
    }
}
