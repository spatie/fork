<?php

namespace Spatie\Fork;

use Closure;
use Exception;

class Fork
{
    private ?Closure $before = null;

    private ?Closure $after = null;

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
            $wrappedCallable = fn () => ['order' => $i, 'result' => $callable()];

            $wrappedCallables[] = $this->runOne($wrappedCallable);
        }

        return $this->waitFor(...$wrappedCallables);
    }

    protected function runOne(callable $callable): Process
    {
        $process = Process::fromCallable($callable);

        socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets);

        [$parentSocket, $childSocket] = $sockets;

        $processId = pcntl_fork();

        if ($this->currentlyInChildProcess($processId)) {
            socket_close($childSocket);

            if ($this->before) {
                ($this->before)();
            }

            socket_write($parentSocket, $process->execute());

            if ($this->after) {
                ($this->after)();
            }

            socket_close($parentSocket);
            exit;
        }

        socket_close($parentSocket);

        return $process
            ->setStartTime(time())
            ->setPid($processId)
            ->setSocket($childSocket);
    }

    private function waitFor(Process ...$runningProcesses): array
    {
        $unsortedOutput = [];

        while (count($runningProcesses)) {
            foreach ($runningProcesses as $key => $process) {
                $processStatus = pcntl_waitpid($process->getPid(), $status, WNOHANG | WUNTRACED);

                $process->setStatus($processStatus);

                if ($process->finishedSuccessfully()) {
                    $unsortedOutput[] = $process->handleSuccess();

                    unset($runningProcesses[$key]);
                } elseif ($processStatus == 0) {
                    if ($process->getStartTime() + $process->getMaxRunTime() < time() || pcntl_wifstopped($status)) {
                        if (! posix_kill($process->getPid(), SIGKILL)) {
                            throw new Exception("Failed to kill {$process->getPid()}: " . posix_strerror(posix_get_last_error()));
                        }

                        unset($runningProcesses[$key]);
                    }
                } else {
                    throw new Exception("Could not reliably manage process {$process->getPid()}");
                }
            }

            if (! count($runningProcesses)) {
                break;
            }

            usleep(1_000);
        }

        $unsortedOutput = array_map(
            fn (string $output) => json_decode($output, true),
            $unsortedOutput
        );

        $sortedOutput = [];

        foreach ($unsortedOutput as $unsortedOutputItem) {
            $sortedOutput[$unsortedOutputItem['order']] = $unsortedOutputItem['result'];
        }

        return $sortedOutput;
    }

    protected function currentlyInChildProcess(int $pid): bool
    {
        return $pid === 0;
    }
}
