<?php

namespace Spatie\Fork;

use Generator;

class Connection
{
    /** @var resource */
    protected $socket;

    /** @var int */
    protected $bufferSize;

    /** @var float */
    protected $timeout;

    protected function __construct($socket, int $bufferSize = 1024, float $timeout = 0.1)
    {
        $this->socket = $socket;
        $this->bufferSize = $bufferSize;
        $this->timeout = $timeout;
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

        while ($payload !== '') {
            $write = [$this->socket];

            $read = null;

            $except = null;

            $selectResult = socket_select($read, $write, $except, $this->timeout);

            if ($selectResult === false) {
                break;
            }

            if ($selectResult <= 0) {
                break;
            }

            $length = strlen($payload);

            $amountOfBytesSent = socket_write($this->socket, $payload, $length);

            if ($amountOfBytesSent === false || $amountOfBytesSent === $length) {
                break;
            }

            $payload = substr($payload, $amountOfBytesSent);
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

            $selectResult = socket_select($read, $write, $except, $this->timeout);

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
