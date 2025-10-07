<?php

declare(strict_types=1);

use DragonZap\AffiliateApi\Client;
use DragonZap\AffiliateApi\Exceptions\ApiException;

$autoloadPath = __DIR__ . '/vendor/autoload.php';

if (file_exists($autoloadPath)) {
    require $autoloadPath;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'DragonZap\\AffiliateApi\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
        $file = __DIR__ . '/src/' . $relativePath . '.php';

        if (file_exists($file)) {
            require $file;
        }
    });
}

$apiKey = getenv('AFFILIATE_API_KEY') ?: '583c999d87d87a086fffeaf370ede4b8834b5ca8ae768a6df43e42b6ae21e12e';

$client = new Client(
    apiKey: $apiKey,
    baseUri: 'http://affiliate.dragonzap.local:8000/api/v1'
);

try {
    echo "Test connection:\n";
    $testResponse = $client->testConnection();
    print_r($testResponse);

    echo "\nProduct list:\n";
    $products = $client->products()->list();
    print_r($products);

    echo "\nPromotions list:\n";
    $promotions = $client->promotions()->list([
        'currency_code' => 'GBP',
        'currency_from_ip' => '1.2.3.4',
    ]);
    print_r($promotions);

    $productId = $products['data']['products'][0]['id'] ?? null;

    if ($productId !== null) {
        echo "\nProduct view (ID: {$productId}):\n";
        $product = $client->products()->retrieve($productId);
        print_r($product);
    } else {
        echo "\nNo products available to retrieve.\n";
    }

    echo "\nCreate webhook:\n";
    $newWebhook = $client->webhooks()->create([
        'event' => 'product.updated',
        'url' => 'https://example.com/webhooks/product-created',
    ]);
    print_r($newWebhook);

    echo "\nWebhook list:\n";
    $webhooks = $client->webhooks()->list();
    print_r($webhooks);
} catch (ApiException $exception) {
    fwrite(STDERR, 'API request failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
