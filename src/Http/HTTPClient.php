<?php

declare(strict_types=1);

namespace NuraBiz\Common\Http;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HTTPClient
{
    private const int HTTP_TIMEOUT = 10;
    private const int HTTP_MAX_DURATION = 30;

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * @throws TransportExceptionInterface\
     */
    public function get(string $url): mixed
    {
        return $this->request('GET', $url);
    }

    public function download(string $url): string
    {
        return $this->httpClient->request('GET', $url, [
            'headers' => ['Accept' => 'application/octet-stream'],
            'timeout' => self::HTTP_TIMEOUT,
            'max_duration' => self::HTTP_MAX_DURATION,
        ])->getContent();
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $attachments Paths to readable files.
     * @throws TransportExceptionInterface
     * @throws HttpExceptionInterface
     * @throws DecodingExceptionInterface
     */
    public function post(string $url, array $data, array $attachments = []): mixed
    {
        if ([] === $attachments) {
            return $this->request('POST', $url, ['json' => $data]);
        }

        $streams = [];
        try {
            foreach ($attachments as $path) {
                if (!\is_string($path) || !is_file($path) || !is_readable($path)) {
                    throw new \RuntimeException('Attachments must be paths to readable files.');
                }
                $stream = fopen($path, 'rb');
                if (false === $stream) {
                    throw new \RuntimeException('Unable to open attachment.');
                }
                $streams[] = $stream;
            }

            return $this->request('POST', $url, ['body' => $data + ['attachments' => $streams]]);
        } finally {
            foreach ($streams as $stream) {
                fclose($stream);
            }
        }
    }

    private function request(string $method, string $url, array $options = []): mixed
    {
        $response = $this->httpClient->request(
            $method,
            $url,
            [
                'headers' => ['Accept' => 'application/json'],
                'timeout' => self::HTTP_TIMEOUT,
                'max_duration' => self::HTTP_MAX_DURATION,
            ] + $options
        );

        // Symfony throws an HTTP exception for a final 3xx, 4xx or 5xx response.
        $response->getContent();

        return $response->toArray(false)['data'] ?? null;
    }
}
