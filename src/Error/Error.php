<?php

namespace NuraBiz\Common\Error;

final readonly class Error implements \JsonSerializable
{
    public function __construct(
        public string $message,
        public mixed $data = null,
        public string|int $code,
    ) {
    }

    public function getCode(): int|string
    {
        return $this->code;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getMessage(): string
    {
        return $this->message;
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
