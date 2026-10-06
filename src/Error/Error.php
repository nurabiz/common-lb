<?php

namespace NuraBiz\Common\Error;

final readonly class Error implements \JsonSerializable
{
    public function __construct(
        public string|int $code,
        public string $message,
        public mixed $data = null,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
