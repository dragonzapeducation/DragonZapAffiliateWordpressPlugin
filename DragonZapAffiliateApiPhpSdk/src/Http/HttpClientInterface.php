<?php

namespace DragonZap\AffiliateApi\Http;

interface HttpClientInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function send(string $method, string $url, array $headers = [], ?string $body = null, ?float $timeout = null): Response;
}
