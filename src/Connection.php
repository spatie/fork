<?php

namespace Spatie\Fork;

use Socket;

class Connection
{
    private function __construct(
        private Socket $socket,
        private int $bufferSize = 1024,
        private float $timeout = 0.1,
    ) {
        socket_set_nonblock($this->socket);
    }

    /**
     * @return self[]
     */
    public static function createPair(): array
    {
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

        while ($payload != '') {
            $write = [$this->socket];

            $read = null;

            $except = null;

            $selectResult = socket_select($read, $write, $except, $this->timeout);

            if ($selectResult === false) {
                break;
            } elseif ($selectResult > 0) {
                $length = strlen($payload);

                $amountOfBytesSent = socket_write($this->socket, $payload, $length);

                if ($amountOfBytesSent === false || $amountOfBytesSent == $length) {
                    break;
                }

                $payload = substr($payload, $amountOfBytesSent);
            }
        }

        return $this;
    }

    public function read(): string
    {
        $output = '';

        socket_set_nonblock($this->socket);

        while (true) {
            $write = null;

            $read = [$this->socket];

            $except = null;

            $selectResult = socket_select($read, $write, $except, $this->timeout);

            if ($selectResult === false) {
                break;
            } elseif ($selectResult > 0) {
                $outputFromSocket = socket_read($this->socket, $this->bufferSize);

                if ($outputFromSocket === false) {
                    break;
                }

                $output .= $outputFromSocket;
            }

            return $output;
        }
    }
}
