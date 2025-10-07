<?php

declare(strict_types=1);

namespace DragonZap\AffiliateApi\Tests;

use DragonZap\AffiliateApi\Client;
use DragonZap\AffiliateApi\Exceptions\ApiException;
use DragonZap\AffiliateApi\Http\HttpClientInterface;
use DragonZap\AffiliateApi\Http\Response;
use PHPUnit\Framework\TestCase;
use Throwable;

final class ClientTest extends TestCase
{
    private Client $client;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $testConnection = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new Client(
            $this->apiKey(),
            $this->baseUri(),
        );
    }

    public function testGetFlattensArrayQueryValues(): void
    {
        $httpClient = new class implements HttpClientInterface {
            public string $capturedUrl = '';

            public function send(string $method, string $url, array $headers = [], ?string $body = null, ?float $timeout = null): Response
            {
                $this->capturedUrl = $url;

                return new Response(200, [], json_encode(['success' => true], JSON_THROW_ON_ERROR));
            }
        };

        $client = new Client('token', 'https://affiliate.example.com/api/v1', $httpClient);

        $client->get('products', [
            'category_slug' => ['design', 'development'],
            'per_page' => 5,
        ]);

        self::assertSame(
            'https://affiliate.example.com/api/v1/products?category_slug=design&category_slug=development&per_page=5',
            $httpClient->capturedUrl
        );
    }

    public function testGetPreservesOrderAcrossMultipleArrayParameters(): void
    {
        $httpClient = new class implements HttpClientInterface {
            public string $capturedUrl = '';

            public function send(string $method, string $url, array $headers = [], ?string $body = null, ?float $timeout = null): Response
            {
                $this->capturedUrl = $url;

                return new Response(200, [], json_encode(['success' => true], JSON_THROW_ON_ERROR));
            }
        };

        $client = new Client('token', 'https://affiliate.example.com/api/v1', $httpClient);

        $client->get('products', [
            'type' => ['course', 'ebook'],
            'category_slug' => ['design', 'development'],
        ]);

        self::assertSame(
            'https://affiliate.example.com/api/v1/products?type=course&type=ebook&category_slug=design&category_slug=development',
            $httpClient->capturedUrl
        );
    }

    public function testTestConnectionReportsScopesAndRestrictions(): void
    {
        $response = $this->getTestConnection();

        self::assertTrue($response['success'] ?? false, 'Affiliate API did not report success for the health check.');
        self::assertArrayHasKey('message', $response);
        self::assertArrayHasKey('data', $response);
        self::assertArrayHasKey('scopes', $response['data']);
        self::assertIsArray($response['data']['scopes']);
        self::assertArrayHasKey('restrictions', $response['data']);
        self::assertIsArray($response['data']['restrictions']);
    }

    public function testProductsListAndRetrieve(): void
    {
        $this->requireScope('products.list');


        $list = $this->client->products()->list(['per_page' => 1]);

        self::assertTrue($list['success'] ?? false, 'Product list response did not indicate success.');
        self::assertArrayHasKey('data', $list);
        self::assertArrayHasKey('products', $list['data']);
        self::assertIsArray($list['data']['products']);

        if ($list['data']['products'] === []) {
            $this->markTestSkipped('Affiliate API returned no products to verify retrieval.');
        }

        $productId = $list['data']['products'][0]['id'] ?? null;
        self::assertNotNull($productId, 'Product list response does not include an ID for the first product.');

        $this->requireScope('products.view');

        $product = $this->client->products()->retrieve($productId);
        self::assertNotNull($product);

        self::assertTrue($product['success'] ?? false, 'Product retrieval response did not indicate success.');
        self::assertSame($productId, $product['data']['product']['id'] ?? null);
    }

    public function testCategoriesList(): void
    {
        $response = $this->client->categories()->list();

        self::assertTrue($response['success'] ?? false, 'Categories list response did not indicate success.');
        self::assertArrayHasKey('data', $response);
        self::assertArrayHasKey('categories', $response['data']);
        self::assertIsArray($response['data']['categories']);
    }

    public function testWebhooksCanBeCreatedAndDeleted(): void
    {
        $this->requireScope('webhooks.manage');


        $list = $this->client->webhooks()->list();


        self::assertArrayHasKey('data', $list);
        self::assertArrayHasKey('supported_events', $list['data']);
        self::assertIsArray($list['data']['supported_events']);

        $webhookId = null;
        $payload = [
            'event' => 'product.updated',
            'url' => sprintf('https://example.com/hooks/%s', $this->uniqueSuffix()),
        ];


        $created = $this->client->webhooks()->create($payload);
        $webhookId = $created['data']['id'] ?? null;

        self::assertTrue($created['success'] ?? false, 'Webhook creation response did not indicate success.');
        self::assertNotNull($webhookId, 'Webhook creation response did not include an ID.');
        self::assertSame($payload['event'], $created['data']['event'] ?? null);
        self::assertSame($payload['url'], $created['data']['url'] ?? null);

        $deleted = $this->client->webhooks()->delete((string) $webhookId);
        self::assertTrue($deleted['success'] ?? false, 'Webhook deletion response did not indicate success.');

        if ($webhookId !== null) {
            try {
                $this->client->webhooks()->delete((string) $webhookId);
            } catch (Throwable) {
                // Ignore cleanup errors.
            }
        }

    }

    public function testBlogProfilesListAndCreateWhenScopeGranted(): void
    {
        if (!$this->hasScope('blogs.accounts.manage')) {
            $this->markTestSkipped('API key does not include the "blogs.accounts.manage" scope.');
        }


        $list = $this->client->blogProfiles()->list();

        self::assertTrue($list['success'] ?? false, 'Blog profiles list response did not indicate success.');
        self::assertArrayHasKey('profiles', $list['data'] ?? []);
        self::assertIsArray($list['data']['profiles']);

        $profile = $this->createBlogProfileForTesting();
        self::assertArrayHasKey('id', $profile);
        self::assertArrayHasKey('identifier', $profile);
    }

    public function testBlogsCreateAndUpdateWhenScopeGranted(): void
    {
        if (!$this->hasScope('blogs.manage') || $this->isRestricted('blogs.manage')) {
            $this->markTestSkipped('API key cannot manage blogs or the scope is restricted.');
        }

        $profile = $this->createBlogProfileForTesting();
        $categoryId = $this->blogCategoryId();

        $createPayload = [
            'title' => sprintf('Integration Blog %s', $this->uniqueSuffix()),
            'content' => '<p>Integration test content</p>',
            'category_slug' => 'low-level-programming',
            'blog_profile_id' => $profile['id'],
        ];

        $blogId = null;


        $created = $this->client->blogs()->create($createPayload);
        $blog = $created['data']['blog'] ?? null;

        self::assertTrue($created['success'] ?? false, 'Blog creation response did not indicate success.');
        self::assertIsArray($blog);
        $blogId = $blog['id'] ?? null;
        self::assertNotNull($blogId, 'Blog creation response did not include an ID.');
        self::assertSame($createPayload['title'], $blog['title'] ?? null);

        $updatedTitle = $createPayload['title'] . ' Updated';
        $updated = $this->client->blogs()->update((string) $blogId, ['title' => $updatedTitle, 'content' => 'howdy']);
        $updatedBlog = $updated['data']['blog'] ?? null;

        self::assertTrue($updated['success'] ?? false, 'Blog update response did not indicate success.');
        self::assertIsArray($updatedBlog);
        self::assertSame($updatedTitle, $updatedBlog['title'] ?? null);
        // Should be false due to content change.
        self::assertFalse($updated['data']['blog']['published']);
    }

    private function apiKey(): string
    {
        return getenv('AFFILIATE_API_KEY') ?: '583c999d87d87a086fffeaf370ede4b8834b5ca8ae768a6df43e42b6ae21e12e';
    }

    private function baseUri(): string
    {
        return getenv('AFFILIATE_API_BASE_URI') ?: 'http://affiliate.dragonzap.local:8000/api/v1';
    }

    private function blogCategoryId(): int
    {
        $category = getenv('AFFILIATE_API_BLOG_CATEGORY_ID');

        return $category !== false ? (int) $category : 4;
    }

    private function getTestConnection(): array
    {
        if ($this->testConnection !== null) {
            return $this->testConnection;
        }

        try {
            $this->testConnection = $this->client->testConnection();
        } catch (Throwable $exception) {
            $this->markTestSkipped('Affiliate API unavailable: ' . $exception->getMessage());
        }

        return $this->testConnection;
    }

    private function hasScope(string $scope): bool
    {
        $connection = $this->getTestConnection();
        $scopes = $connection['data']['scopes'] ?? [];

        return is_array($scopes) && in_array($scope, $scopes, true);
    }

    private function isRestricted(string $scope): bool
    {
        $connection = $this->getTestConnection();
        $restrictions = $connection['data']['restrictions'] ?? [];

        return is_array($restrictions) && in_array($scope, $restrictions, true);
    }

    private function requireScope(string $scope): void
    {
        if (!$this->hasScope($scope)) {
            $this->markTestSkipped(sprintf('API key does not include the "%s" scope.', $scope));
        }
    }

    /**
     * @return array{id:int|string,identifier:string}
     */
    private function createBlogProfileForTesting(): array
    {
        $this->requireScope('blogs.accounts.manage');

        $payload = [
            'name' => sprintf('Integration Profile %s', $this->uniqueSuffix()),
            'identifier' => sprintf('integration-%s', $this->uniqueSuffix()),
        ];

  
        $response = $this->client->blogProfiles()->create($payload);
    

        $profile = $response['data']['profile'] ?? null;
        self::assertTrue($response['success'] ?? false, 'Blog profile creation response did not indicate success.');
        self::assertIsArray($profile);

        $id = $profile['id'] ?? null;
        $identifier = $profile['identifier'] ?? null;

        self::assertNotNull($id, 'Blog profile creation response did not include an ID.');
        self::assertNotNull($identifier, 'Blog profile creation response did not include an identifier.');

        return ['id' => $id, 'identifier' => $identifier];
    }

    private function uniqueSuffix(): string
    {
        return strtolower(str_replace('.', '', uniqid('', true)));
    }
}
