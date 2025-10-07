<?php

declare(strict_types=1);

namespace DragonZap\AffiliateApi\Tests;

use DragonZap\AffiliateApi\Client;
use DragonZap\AffiliateApi\Http\HttpClientInterface;
use DragonZap\AffiliateApi\Http\Response;
use PHPUnit\Framework\TestCase;

final class PromotionsResourceTest extends TestCase
{
    public function testListReturnsDecodedPayloadWithCurrencyFilters(): void
    {
        $expectedPayload = [
            'success' => true,
            'data' => [
                'promotions' => [
                    ['id' => 101, 'title' => 'Spring Sale'],
                ],
            ],
        ];

        $responseBody = json_encode($expectedPayload, JSON_THROW_ON_ERROR);

        $httpClient = new class(new Response(200, [], $responseBody)) implements HttpClientInterface {
            public string $method;
            public string $url;
            public array $headers = [];
            public ?string $body = null;

            public function __construct(private readonly Response $response)
            {
            }

            public function send(string $method, string $url, array $headers = [], ?string $body = null, ?float $timeout = null): Response
            {
                $this->method = $method;
                $this->url = $url;
                $this->headers = $headers;
                $this->body = $body;

                return $this->response;
            }
        };

        $client = new Client('test-key', 'https://affiliate.dragonzap.local:8000/api/v1', $httpClient);

        $query = [
            'currency_code' => 'GBP',
            'currency_from_ip' => '1.2.3.4',
        ];

        $payload = $client->promotions()->list($query);

        self::assertSame($expectedPayload, $payload);
        self::assertSame('GET', $httpClient->method);
        self::assertSame('/api/v1/promotions', parse_url($httpClient->url, PHP_URL_PATH));

        $parsedQuery = [];
        parse_str((string) parse_url($httpClient->url, PHP_URL_QUERY), $parsedQuery);

        self::assertArrayHasKey('currency_code', $parsedQuery);
        self::assertSame('GBP', $parsedQuery['currency_code']);
        self::assertArrayHasKey('currency_from_ip', $parsedQuery);
        self::assertSame('1.2.3.4', $parsedQuery['currency_from_ip']);
    }
}
