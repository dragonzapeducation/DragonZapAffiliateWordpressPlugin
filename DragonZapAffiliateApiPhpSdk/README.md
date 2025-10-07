# Dragon Zap Affiliate API PHP SDK

A lightweight PHP SDK for the [Dragon Zap Affiliate API](affiliate-api.md). It provides convenient wrappers for the documented endpoints so you can list products, manage blogs, and configure webhooks with minimal boilerplate.

[Dragon Zap](https://dragonzap.com) is a publishing company based in London that provide video courses and books that teach software engineering. Affiliates can make money by sharing Dragon Zap's video courses to people, when they make a purcahse the affilaite gets paid. This PHP SDK aims to streamline the money making process for affiliates, they can use this SDK to search our products in real time which they can then provide to their audience with the affiliate ID automatically attached. The SDK also allows an affiliate to create blog posts on the Dragon Zap platform, if a Dragon Zap customer clicks this blog post then the affilaite will get commission if that click resulted in a sale in the near future. 

Use this SDK to automate your affiliate operations

## Installation

Add the package to your project via Composer:

```bash
composer require dragonzap/affiliate-api-php-sdk
```

> **Note:** The SDK has no runtime dependencies other than PHP 8.1+. The provided `CurlHttpClient` implementation requires the `ext-curl` extension.

## Getting Started

```php
use DragonZap\AffiliateApi\Client;

$client = new Client(
    apiKey: 'your-api-key',
    baseUri: 'https://affiliate.dragonzap.com/api/v1'
);

$response = $client->testConnection();
```

### Listing Products

```php
$products = $client->products()->list([
    'per_page' => 5,
    'currency_code' => 'EUR',
]);
```

### Listing Promotions

```php
$promotions = $client->promotions()->list([
    'currency_code' => 'GBP',
    'currency_from_ip' => '1.2.3.4',
]);
```
> **Multi-value filters:** Pass arrays (e.g. `['category_slug' => ['design', 'development']]`) to send repeated query parameters such as `category_slug=design&category_slug=development` when filtering results.

### Retrieving a Product

```php
$product = $client->products()->retrieve(417, [
    'currency_from_ip' => '8.8.8.8',
]);
```

### Managing Blogs

```php
// Create a blog profile
$profile = $client->blogProfiles()->create([
    'name' => 'My Affiliate Blog',
    'identifier' => 'affiliate-blog-1',
]);

// Discover available categories
$categories = $client->categories()->list();

// Create a new blog draft
$blog = $client->blogs()->create([
    'title' => 'Spring Updates',
    'content' => '<p>Latest platform news...</p>',
    'category_slug' => 'low-level-programming',
    'blog_profile_id' => $profile['data']['profile']['id'],
]);

// Partially update an existing blog (PATCH)
$client->blogs()->update($blog['data']['blog']['id'], [
    'title' => 'Spring Updates (Revised)',
]);

// Replace an existing blog with a full payload using PUT
$client->blogs()->replace($blog['data']['blog']['id'], [
    'title' => 'Spring Updates (Final Copy)',
    'content' => '<p>Updated content...</p>',
    'category_slug' => 'low-level-programming',
    'blog_profile_id' => $profile['data']['profile']['id'],
]);
```

### Working with Webhooks

```php
$webhooks = $client->webhooks()->list();

$newWebhook = $client->webhooks()->create([
    'event' => 'product.updated',
    'url' => 'https://hooks.example.com/product-updated',
]);

$client->webhooks()->delete($newWebhook['data']['id']);
```

## Advanced Usage

### Custom HTTP Clients

The SDK ships with a simple cURL-based client, but you can provide your own implementation to integrate with your preferred HTTP stack or to facilitate testing.

```php
use DragonZap\AffiliateApi\Client;
use DragonZap\AffiliateApi\Http\HttpClientInterface;
use DragonZap\AffiliateApi\Http\Response;

class SymfonyHttpClientAdapter implements HttpClientInterface
{
    public function __construct(private readonly Symfony\Component\HttpClient\HttpClientInterface $httpClient)
    {
    }

    public function send(string $method, string $url, array $headers = [], ?string $body = null, ?float $timeout = null): Response
    {
        $response = $this->httpClient->request($method, $url, [
            'headers' => $headers,
            'body' => $body,
            'timeout' => $timeout,
        ]);

        return new Response(
            $response->getStatusCode(),
            $response->getHeaders(false),
            $response->getContent(false)
        );
    }
}

$client = new Client('key', 'https://affiliate.example.com/api/v1', new SymfonyHttpClientAdapter($symfonyClient));
```

### Handling Errors

Requests that receive non-2xx responses or a JSON payload with `success: false` throw an `ApiException`. Catch the exception to inspect the message or retry logic as needed.

```php
use DragonZap\AffiliateApi\Exceptions\ApiException;

try {
    $client->products()->list();
} catch (ApiException $exception) {
    echo $exception->getMessage();
}
```

## Testing

Run the unit test suite with:

```bash
composer test
```

The test suite includes a fake HTTP client to avoid outbound network requests.
