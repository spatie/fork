<?php

namespace Spatie\Fork;

use Generator;
use RuntimeException;
use Socket;

class Connection
{
    protected function __construct(
        protected Socket $socket,
        protected int $bufferSize = 1024,
        protected int $writeTimeoutSeconds = 5,
    ) {
        socket_set_nonblock($this->socket);
    }

    /**
     * @return self[]
     */
    public static function createPair(): array
    {
        $sockets = [];

        socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets);

        [$socketToParent, $socketToChild] = $sockets;

        return [
            new self($socketToParent),
            new self($socketToChild),
        ];
    }

    public function close(): self
    {
        socket_close($this->socket);

        return $this;
    }

    public function write(string $payload): self
    {
        socket_set_nonblock($this->socket);
        $deadline = hrtime(true) + $this->writeTimeoutSeconds * 1_000_000_000;

        while ($payload !== '') {
            $remainingMicroseconds = (int) ceil(($deadline - hrtime(true)) / 1_000);

            if ($remainingMicroseconds <= 0) {
                throw new RuntimeException('Timed out while writing task output.');
            }

            $write = [$this->socket];

            $read = null;

            $except = null;

            $selectResult = @socket_select(
                $read,
                $write,
                $except,
                intdiv($remainingMicroseconds, 1_000_000),
                $remainingMicroseconds % 1_000_000,
            );

            if ($selectResult === false && socket_last_error() === SOCKET_EINTR) {
                continue;
            }

            if ($selectResult === false) {
                throw new RuntimeException('Could not select the task output socket for writing.');
            }

            if ($selectResult === 0) {
                throw new RuntimeException('Timed out while writing task output.');
            }

            $amountOfBytesSent = @socket_write($this->socket, $payload, strlen($payload));

            if ($amountOfBytesSent === false && in_array(socket_last_error($this->socket), [SOCKET_EINTR, SOCKET_EAGAIN])) {
                continue;
            }

            if ($amountOfBytesSent === false || $amountOfBytesSent === 0) {
                throw new RuntimeException('Could not write the complete task output.');
            }

            $payload = substr($payload, $amountOfBytesSent);
            $deadline = hrtime(true) + $this->writeTimeoutSeconds * 1_000_000_000;
        }

        return $this;
    }

    public function read(): Generator
    {
        socket_set_nonblock($this->socket);

        while (true) {
            $read = [$this->socket];

            $write = null;

            $except = null;

            $selectResult = @socket_select($read, $write, $except, 0);

            if ($selectResult === false && socket_last_error() === SOCKET_EINTR) {
                continue;
            }

            if ($selectResult === false) {
                break;
            }

            if ($selectResult <= 0) {
                break;
            }

            $outputFromSocket = socket_read($this->socket, $this->bufferSize);

            if ($outputFromSocket === false || $outputFromSocket === '') {
                break;
            }

            yield $outputFromSocket;
        }
    }
}
