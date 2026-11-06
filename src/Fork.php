<?php

namespace Spatie\Fork;

use Closure;
use Exception;
use Spatie\Fork\Exceptions\CouldNotManageTask;
use Throwable;

class Fork
{
    protected ?Closure $toExecuteBeforeInChildTask = null;

    protected ?Closure $toExecuteBeforeInParentTask = null;

    protected ?Closure $toExecuteAfterInChildTask = null;

    protected ?Closure $toExecuteAfterInParentTask = null;

    protected ?Closure $toExecuteAfterWithOrderInChildTask = null;

    protected ?Closure $toExecuteAfterWithOrderInParentTask = null;

    protected ?Closure $catchCallback = null;

    protected ?int $concurrent = null;

    /** @var Task[] */
    protected array $queue = [];

    /** @var Task[] */
    protected array $runningTasks = [];

    public function __construct()
    {
        if (! function_exists('pcntl_fork')) {
            throw new Exception('Cannot create process forks: PCNTL is not supported on this system.');
        }
    }

    public static function new(): self
    {
        return new self;
    }

    public function before(?callable $child = null, ?callable $parent = null): self
    {
        $this->toExecuteBeforeInChildTask = $child;
        $this->toExecuteBeforeInParentTask = $parent;

        return $this;
    }

    public function after(?callable $child = null, ?callable $parent = null): self
    {
        $this->toExecuteAfterInChildTask = $child;
        $this->toExecuteAfterInParentTask = $parent;

        return $this;
    }

    public function afterWithOrder(?callable $child = null, ?callable $parent = null): self
    {
        $this->toExecuteAfterWithOrderInChildTask = $child === null ? null : $child(...);
        $this->toExecuteAfterWithOrderInParentTask = $parent === null ? null : $parent(...);

        return $this;
    }

    /**
     * Without a catch callback, an exception thrown in a child task makes it return an empty string. With one, the
     * exception is sent to the parent process and passed to the callback, together with the task's order. Whatever
     * the callback returns becomes the output of the task.
     */
    public function catch(callable $callback): self
    {
        $this->catchCallback = $callback(...);

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
            $tasks[] = Task::fromCallable($callable, $order)
                ->setCatchExceptions($this->catchCallback !== null);
        }

        return $this->waitFor(...$tasks);
    }

    protected function waitFor(Task ...$queue): array
    {
        $output = [];

        $previousSignalHandlers = [];

        foreach ([SIGQUIT, SIGTERM, SIGINT] as $signal) {
            $previousSignalHandlers[$signal] = pcntl_signal_get_handler($signal);
        }

        try {
            $this->listenForSignals();
            $this->startRunning(...$queue);

            while ($this->isRunning()) {
                foreach ($this->runningTasks as $task) {
                    if (! $task->isFinished()) {
                        continue;
                    }

                    $output[$task->order()] = $this->finishTask($task);

                    $this->shiftTaskFromQueue();
                }

                if ($this->isRunning()) {
                    usleep(1_000);
                }
            }
        } finally {
            foreach ($previousSignalHandlers as $signal => $handler) {
                pcntl_signal($signal, $handler);
            }
        }

        return $output;
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

        if ($task->exception() && $this->catchCallback) {
            $output = ($this->catchCallback)($task->exception(), $task->order());
        }

        if ($this->toExecuteAfterInParentTask) {
            ($this->toExecuteAfterInParentTask)($output);
        }

        if ($this->toExecuteAfterWithOrderInParentTask) {
            ($this->toExecuteAfterWithOrderInParentTask)($output, $task->order());
        }

        unset($this->runningTasks[$task->order()]);

        return $output;
    }

    protected function forkForTask(Task $task): ?Task
    {
        [$socketToParent, $socketToChild] = Connection::createPair();

        try {
            $processId = @pcntl_fork();

            if ($processId === -1) {
                throw CouldNotManageTask::forkFailed(pcntl_strerror(pcntl_get_last_error()));
            }
        } catch (Throwable $exception) {
            $socketToParent->close();
            $socketToChild->close();

            if (extension_loaded('posix')) {
                foreach ($this->runningTasks as $runningTask) {
                    posix_kill($runningTask->pid(), SIGKILL);
                }

                foreach ($this->runningTasks as $runningTask) {
                    do {
                        $waitResult = pcntl_waitpid($runningTask->pid(), $status);
                    } while ($waitResult === -1 && pcntl_get_last_error() === PCNTL_EINTR);
                }
            }

            foreach ($this->runningTasks as $runningTask) {
                $runningTask->closeConnection();
            }

            $this->runningTasks = [];
            $this->queue = [];

            throw $exception;
        }

        if ($this->currentlyInChildTask($processId)) {
            $socketToChild->close();

            try {
                $this->executeInChildTask($task, $socketToParent);
            } finally {
                if (! extension_loaded('posix')) {
                    exit();
                }

                posix_kill(getmypid(), SIGKILL);
            }
        }

        $socketToParent->close();

        return $task
            ->setStartTime(time())
            ->setPid($processId)
            ->setConnection($socketToChild);
    }

    protected function listenForSignals(): void
    {
        pcntl_async_signals(true);

        pcntl_signal(SIGQUIT, fn () => $this->exit());
        pcntl_signal(SIGTERM, fn () => $this->exit());
        pcntl_signal(SIGINT, fn () => $this->exit());
    }

    protected function exit(): void
    {
        if (! extension_loaded('posix')) {
            exit();
        }

        foreach ($this->runningTasks as $task) {
            posix_kill($task->pid(), SIGKILL);
        }

        posix_kill(getmypid(), SIGKILL);
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

        try {
            $connectionToParent->write($output);
        } catch (Throwable) {
            // Exit directly so the SIGKILL in forkForTask() cannot mask a failed transfer.
            exit(1);
        }

        if ($this->toExecuteAfterInChildTask) {
            ($this->toExecuteAfterInChildTask)($output);
        }

        if ($this->toExecuteAfterWithOrderInChildTask) {
            ($this->toExecuteAfterWithOrderInChildTask)($output, $task->order());
        }

        $connectionToParent->close();
    }

    protected function shiftTaskFromQueue(): void
    {
        if (! count($this->queue)) {
            return;
        }

        $firstTask = array_shift($this->queue);

        $this->runningTasks[] = $this->runTask($firstTask);
    }

    protected function startRunning(Task ...$queue): void
    {
        $this->queue = $queue;

        foreach ($this->queue as $task) {
            $this->runningTasks[$task->order()] = $this->runTask($task);

            unset($this->queue[$task->order()]);

            if ($this->concurrencyLimitReached()) {
                break;
            }
        }
    }

    protected function isRunning(): bool
    {
        return count($this->runningTasks) > 0;
    }

    protected function concurrencyLimitReached(): bool
    {
        return $this->concurrent && count($this->runningTasks) >= $this->concurrent;
    }
}
