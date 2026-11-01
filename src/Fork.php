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

    /** @var Task[] */
    protected array $queue = [];

    /** @var Task[] */
    protected array $runningTasks = [];

    /** @var array<int, callable|int> The handlers that were installed before run(), keyed by signal */
    protected array $previousSignalHandlers = [];

    protected ?int $receivedSignal = null;

    protected mixed $receivedSignalInfo = null;

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

    protected function waitFor(Task ...$queue): array
    {
        $output = [];

        $this->listenForSignals();

        try {
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
            $this->restoreSignalHandlers();
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

        if ($this->toExecuteAfterInParentTask) {
            ($this->toExecuteAfterInParentTask)($output);
        }

        unset($this->runningTasks[$task->order()]);

        return $output;
    }

    protected function forkForTask(Task $task): ?Task
    {
        [$socketToParent, $socketToChild] = Connection::createPair();

        $processId = pcntl_fork();

        if ($this->currentlyInChildTask($processId)) {
            $socketToChild->close();

            $this->resetSignalHandlersInChildTask();

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

        foreach ([SIGQUIT, SIGTERM, SIGINT] as $signal) {
            $previousHandler = pcntl_signal_get_handler($signal);

            // A signal that was being ignored stays ignored
            if ($previousHandler === SIG_IGN) {
                continue;
            }

            $this->previousSignalHandlers[$signal] = $previousHandler;

            pcntl_signal($signal, function (int $signal, mixed $signalInfo = null) {
                $this->receivedSignal = $signal;
                $this->receivedSignalInfo = $signalInfo;

                $this->exit();
            });
        }
    }

    protected function restoreSignalHandlers(): void
    {
        foreach ($this->previousSignalHandlers as $signal => $handler) {
            pcntl_signal($signal, $handler);
        }

        $this->previousSignalHandlers = [];
    }

    /**
     * A child task should just end when it is asked to stop, rather than running the parent's handler (which would
     * kill the other tasks it knows about).
     */
    protected function resetSignalHandlersInChildTask(): void
    {
        foreach (array_keys($this->previousSignalHandlers) as $signal) {
            pcntl_signal($signal, SIG_DFL);
        }
    }

    protected function exit(): void
    {
        if (! extension_loaded('posix')) {
            exit();
        }

        foreach ($this->runningTasks as $task) {
            posix_kill($task->pid(), SIGKILL);
        }

        // Don't start the tasks that are still waiting
        $this->queue = [];

        if ($this->receivedSignal === null) {
            posix_kill(getmypid(), SIGKILL);

            return;
        }

        // Hand the signal on to whatever handled it before run(): an application's own handler (eg. a queue worker's
        // graceful shutdown) now gets to run, and the default handler ends the process, as before. The handler is called
        // directly, because PHP drops a signal that is raised again from inside its own handler.
        $signal = $this->receivedSignal;
        $previousHandler = $this->previousSignalHandlers[$signal] ?? SIG_DFL;
        $this->receivedSignal = null;

        $this->restoreSignalHandlers();

        if (is_callable($previousHandler)) {
            $previousHandler($signal, $this->receivedSignalInfo);

            return;
        }

        posix_kill(getmypid(), $signal);
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
