<?php

namespace DragonZap\AffiliateApi\Http;

class Response
{
    /**
     * @param array<string, array<int, string>|string> $headers
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly array $headers,
        private readonly string $body
    ) {
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
