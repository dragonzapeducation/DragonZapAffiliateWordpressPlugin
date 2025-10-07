<?php

namespace DragonZap\AffiliateApi\Resources;

use DragonZap\AffiliateApi\Client;

class ProductsResource
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * List storefront products.
     *
     * @param array<string, scalar|array<array-key, scalar>> $query
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        return $this->client->get('products', $query);
    }

    /**
     * Retrieve a single product by ID.
     *
     * @param int|string $productId
     * @param array<string, scalar|array<array-key, scalar>> $query
     * @return array<string, mixed>
     */
    public function retrieve(int|string $productId, array $query = []): array
    {
        return $this->client->get(sprintf('products/%s', $productId), $query);
    }
}
