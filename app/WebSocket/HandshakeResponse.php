<?php

namespace App\WebSocket;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\MessageInterface;

class HandshakeResponse extends Response
{
    public function withHeader($name, $value): MessageInterface
    {
        return parent::withHeader($name, $this->stringifyScalar($value));
    }

    public function withAddedHeader($name, $value): MessageInterface
    {
        return parent::withAddedHeader($name, $this->stringifyScalar($value));
    }

    private function stringifyScalar($value): string|array
    {
        if (is_scalar($value) && ! is_string($value)) {
            return (string) $value;
        }

        return $value;
    }
}
