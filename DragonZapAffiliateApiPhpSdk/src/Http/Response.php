<?php

namespace DragonZap\AffiliateApi\Http;

class Response
{
    private int $statusCode;

    /**
     * @var array<string, array<int, string>|string>
     */
    private array $headers;

    private string $body;

    /**
     * @param array<string, array<int, string>|string> $headers
     */
    public function __construct(int $statusCode, array $headers, string $body)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
