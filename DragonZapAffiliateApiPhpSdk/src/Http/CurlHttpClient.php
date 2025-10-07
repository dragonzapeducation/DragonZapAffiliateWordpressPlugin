<?php

namespace DragonZap\AffiliateApi\Http;

use RuntimeException;

class CurlHttpClient implements HttpClientInterface
{
    public function __construct(private readonly ?float $timeout = 10.0)
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL extension is required to use CurlHttpClient.');
        }
    }

    /**
     * @param array<string, string> $headers
     */
    public function send(string $method, string $url, array $headers = [], ?string $body = null, ?float $timeout = null): Response
    {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new RuntimeException('Unable to initialize cURL session.');
        }

        $timeout ??= $this->timeout;

        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = $key . ': ' . $value;
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HEADER => true,
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($handle);

        if ($response === false) {
            $error = curl_error($handle);
            $code = curl_errno($handle);
            curl_close($handle);
            throw new RuntimeException('cURL request failed: ' . $error, $code);
        }

        $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $headerSize = curl_getinfo($handle, CURLINFO_HEADER_SIZE);

        curl_close($handle);

        $rawHeaders = substr($response, 0, $headerSize) ?: '';
        $bodyContent = substr($response, $headerSize) ?: '';

        $parsedHeaders = $this->parseHeaders($rawHeaders);

        return new Response($statusCode ?: 0, $parsedHeaders, $bodyContent);
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    private function parseHeaders(string $rawHeaders): array
    {
        $headers = [];
        $lines = preg_split('/\r?\n/', trim($rawHeaders));

        if ($lines === false) {
            return $headers;
        }

        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = array_map('trim', explode(':', $line, 2));
                if (isset($headers[$name])) {
                    if (!is_array($headers[$name])) {
                        $headers[$name] = [$headers[$name]];
                    }
                    $headers[$name][] = $value;
                } else {
                    $headers[$name] = $value;
                }
            }
        }

        return $headers;
    }
}
