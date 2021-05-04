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

    protected ?int $concurrent = null;

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

    public function concurrent(int $concurrent): self
    {
        $this->concurrent = $concurrent;

        return $this;
    }

    public function run(callable ...$callables): array
    {
        $tasks = [];

        foreach ($callables as $order => $callable) {
            $tasks[] = Task::fromCallable($callable, $order);
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

    protected function runTask(Task $task): Task
    {
        if ($this->toExecuteBeforeInParentTask) {
            ($this->toExecuteBeforeInParentTask)();
        }

        return $this->forkForTask($task);
    }

    protected function finishTask(Task $task): mixed
    {
        $output = $task->output();

        if ($this->toExecuteAfterInParentTask) {
            ($this->toExecuteAfterInParentTask)($output);
        }

        return $output;
    }

    protected function waitFor(Task ...$queue): array
    {
        $output = [];

        $running = [];

        $amountRunning = 0;

        foreach ($queue as $i => $task) {
            $running[] = $this->runTask($task);

            unset($queue[$i]);

            $amountRunning += 1;

            if ($this->concurrent && $amountRunning >= $this->concurrent) {
                break;
            }
        }

        while (count($running)) {
            foreach ($running as $key => $task) {
                if (! $task->isFinished()) {
                    continue;
                }

                $output[$task->order()] = $this->finishTask($task);

                unset($running[$key]);

                if (count($queue)) {
                    $i = array_key_first($queue);

                    $running[] = $this->runTask($queue[$i]);

                    unset($queue[$i]);
                }
            }

            if (! count($running)) {
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
