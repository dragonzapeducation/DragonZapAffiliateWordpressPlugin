<?php

namespace DragonZap\AffiliateApi;

use DragonZap\AffiliateApi\Exceptions\ApiException;
use DragonZap\AffiliateApi\Http\CurlHttpClient;
use DragonZap\AffiliateApi\Http\HttpClientInterface;
use DragonZap\AffiliateApi\Http\Response;
use DragonZap\AffiliateApi\Resources\BlogProfilesResource;
use DragonZap\AffiliateApi\Resources\BlogsResource;
use DragonZap\AffiliateApi\Resources\CategoriesResource;
use DragonZap\AffiliateApi\Resources\PromotionsResource;
use DragonZap\AffiliateApi\Resources\ProductsResource;
use DragonZap\AffiliateApi\Resources\WebhooksResource;

class Client
{
    private HttpClientInterface $httpClient;

    private string $apiKey;

    private string $baseUri;

    /**
     * @var array<string, string>
     */
    private array $defaultHeaders;

    /**
     * @param array<string, string> $defaultHeaders
     */
    public function __construct(string $apiKey, string $baseUri, ?HttpClientInterface $httpClient = null, array $defaultHeaders = [])
    {
        $this->apiKey = $apiKey;
        $this->baseUri = rtrim($baseUri, '/') . '/';
        $this->httpClient = $httpClient ?? new CurlHttpClient();
        $this->defaultHeaders = $defaultHeaders;
    }

    /**
     * Performs a health check request against the `/test` endpoint.
     *
     * @return array<string, mixed>
     */
    public function testConnection(): array
    {
        return $this->get('test');
    }

    public function products(): ProductsResource
    {
        return new ProductsResource($this);
    }

    public function promotions(): PromotionsResource
    {
        return new PromotionsResource($this);
    }

    public function categories(): CategoriesResource
    {
        return new CategoriesResource($this);
    }

    public function blogProfiles(): BlogProfilesResource
    {
        return new BlogProfilesResource($this);
    }

    public function blogs(): BlogsResource
    {
        return new BlogsResource($this);
    }

    public function webhooks(): WebhooksResource
    {
        return new WebhooksResource($this);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        if (!empty($query)) {
            $queryString = $this->buildQueryString($query);
            if ($queryString !== '') {
                $path .= '?' . $queryString;
            }
        }

        return $this->request('GET', $path);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload = []): array
    {
        return $this->request('POST', $path, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function patch(string $path, array $payload = []): array
    {
        return $this->request('PATCH', $path, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function put(string $path, array $payload = []): array
    {
        return $this->request('PUT', $path, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function delete(string $path, array $payload = []): array
    {
        return $this->request('DELETE', $path, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $payload = []): array
    {
        $url = $this->baseUri . ltrim($path, '/');

        $headers = array_merge([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        ], $this->defaultHeaders);

        $body = null;
        if (!empty($payload)) {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
            $headers['Content-Type'] = 'application/json';
        }

        try {
            $response = $this->httpClient->send($method, $url, $headers, $body);
        } catch (\Throwable $exception) {
            throw new ApiException($exception->getMessage(), (int) $exception->getCode(), null, $exception);
        }
      
        return $this->parseResponse($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseResponse(Response $response): array
    {
        $body = $response->getBody();
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new ApiException('Unable to decode API response: ' . json_last_error_msg(), $response->getStatusCode());
        }

        if ($response->getStatusCode() >= 400 || ($decoded['success'] ?? true) === false) {
            $message = $decoded['message'] ?? 'Affiliate API request failed.';
            throw new ApiException($message, $response->getStatusCode());
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function buildQueryString(array $query): string
    {
        $pairs = [];

        foreach ($query as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $pairs[] = [$key => $item];
                }
                continue;
            }

            $pairs[] = [$key => $value];
        }

        $encoded = [];

        foreach ($pairs as $pair) {
            $queryPart = http_build_query($pair, '', '&', PHP_QUERY_RFC3986);

            if ($queryPart !== '') {
                $encoded[] = $queryPart;
            }
        }

        return implode('&', $encoded);
    }
}
