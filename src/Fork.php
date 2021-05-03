<?php

namespace Spatie\Fork;

use Closure;
use Exception;

class Fork
{
    protected ?Closure $toExecuteBeforeInChildTask = null;
    protected ?Closure $toExecuteBeforeInParentTask = null;

    protected ?Closure $toExecuteAfterInChildTask = null;
    protected ?Closure $toExecuteAfterInParentTask = null;

    public function __construct()
    {
        if (! function_exists('pcntl_fork')) {
            throw new Exception("Cannot create process forks: PCNTL is not supported on this system.");
        }
    }

    public static function new(): self
    {
        return new self();
    }

    public function before(callable $child = null, callable $parent = null): self
    {
        $this->toExecuteBeforeInChildTask = $child;
        $this->toExecuteBeforeInParentTask = $parent;

        return $this;
    }

    public function after(callable $child = null, callable $parent = null): self
    {
        $this->toExecuteAfterInChildTask = $child;
        $this->toExecuteAfterInParentTask = $parent;

        return $this;
    }

    public function run(callable ...$callables): array
    {
        $tasks = [];

        foreach ($callables as $order => $callable) {
            if ($this->toExecuteBeforeInParentTask) {
                ($this->toExecuteBeforeInParentTask)();
            }

            $task = Task::fromCallable($callable, $order);

            $tasks[] = $this->forkForTask($task);
        }

        return $this->waitFor(...$tasks);
    }

    protected function forkForTask(Task $task): Task
    {
        [$socketToParent, $socketToChild] = Connection::createPair();

        $processId = pcntl_fork();

        if ($this->currentlyInChildTask($processId)) {
            $socketToChild->close();

            $this->executeInChildTask($task, $socketToParent);

            exit;
        }

        $socketToParent->close();

        return $task
            ->setStartTime(time())
            ->setPid($processId)
            ->setConnection($socketToChild);
    }

    protected function waitFor(Task ...$runningTasks): array
    {
        $output = [];

        while (count($runningTasks)) {
            foreach ($runningTasks as $key => $task) {
                if ($task->isFinished()) {
                    $taskOutput = $task->output();

                    $output[$task->order()] = $taskOutput;

                    unset($runningTasks[$key]);

                    if ($this->toExecuteAfterInParentTask) {
                        ($this->toExecuteAfterInParentTask)($taskOutput);
                    }
                }
            }

            if (! count($runningTasks)) {
                break;
            }

            usleep(1_000);
        }

        return $output;
    }

    protected function currentlyInChildTask(int $pid): bool
    {
        return $pid === 0;
    }

    protected function executeInChildTask(
        Task $task,
        Connection $connectionToParent,
    ): void {
        if ($this->toExecuteBeforeInChildTask) {
            ($this->toExecuteBeforeInChildTask)();
        }

        $output = $task->execute();

        $connectionToParent->write($output);

        if ($this->toExecuteAfterInChildTask) {
            ($this->toExecuteAfterInChildTask)($output);
        }

        $connectionToParent->close();
    }
}
