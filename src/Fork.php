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
        $waitFor = [];

        foreach ($callables as $i => $callable) {
            $waitFor[$i] = $this->runOne($callable);
        }

        ksort($waitFor);

        return $this->wait(...$waitFor);
    }

    private function runOne(callable $callable): Process
    {
        $process = Process::fromCallable($callable);

        socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets);

        [$parentSocket, $childSocket] = $sockets;

        $pid = pcntl_fork();

        if ($pid == 0) {
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
            ->setPid($pid)
            ->setSocket($childSocket);
    }

    private function wait(Process ...$processes): array
    {
        $output = [];

        while (count($processes)) {
            foreach ($processes as $key => $process) {
                $processStatus = pcntl_waitpid($process->getPid(), $status, WNOHANG | WUNTRACED);

                if ($processStatus == $process->getPid()) {
                    $output[] = $process->read();

                    socket_close($process->getSocket());

                    $process->triggerSuccess();

                    unset($processes[$key]);
                } elseif ($processStatus == 0) {
                    if ($process->getStartTime() + $process->getMaxRunTime() < time() || pcntl_wifstopped($status)) {
                        if (! posix_kill($process->getPid(), SIGKILL)) {
                            throw new Exception("Failed to kill {$process->getPid()}: " . posix_strerror(posix_get_last_error()));
                        }

                        unset($processes[$key]);
                    }
                } else {
                    throw new Exception("Could not reliably manage process {$process->getPid()}");
                }
            }

            if (! count($processes)) {
                break;
            }

            usleep(1_000);
        }

        return $output;
    }
}
