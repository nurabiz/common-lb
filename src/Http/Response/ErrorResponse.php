<?php

namespace NuraBiz\Common\Http\Response;

use NuraBiz\Common\Error\Error;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ErrorResponse extends JsonResponse
{
    public function __construct(Error $error, int $status = self::HTTP_BAD_REQUEST, array $headers = [])
    {
        if ($status < 400 || $status >= 600) {
            throw new \InvalidArgumentException('An error response requires a 4xx or 5xx status.');
        }

        parent::__construct([
            'status' => $status,
            'data' => $error,
            'message' => $error->message,
        ], $status, $headers);
    }
}
