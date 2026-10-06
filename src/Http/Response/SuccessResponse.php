<?php
declare(strict_types=1);

namespace NuraBiz\Common\Http\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

final class SuccessResponse extends JsonResponse
{
    public function __construct(?string $message = null, mixed $data = null, int $status = self::HTTP_OK, array $headers = [])
    {
        if ($status < 200 || $status >= 300 || \in_array($status, [self::HTTP_NO_CONTENT, self::HTTP_RESET_CONTENT], true)) {
            throw new \InvalidArgumentException('A success response requires a 2xx status that allows a response body.');
        }

        $result = [
            'data' => $data
        ];

        if ($status !== self::HTTP_OK) {
            $result['status'] = $status;
        }

        if (null !== $message) {
            $result['message'] = $message;
        }

        parent::__construct($result, $status, $headers);
    }
}
