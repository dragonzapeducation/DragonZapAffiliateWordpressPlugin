<?php

namespace DragonZap\AffiliateApi\Resources;

use DragonZap\AffiliateApi\Client;

class CategoriesResource
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * List available content categories.
     *
     * @param array<string, scalar|array<array-key, scalar>> $query
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        return $this->client->get('categories', $query);
    }

    /**
     * Retrieve a single category by slug.
     *
     * @param int|string $category
     * @param array<string, scalar|array<array-key, scalar>> $query
     * @return array<string, mixed>
     */
    public function retrieve(int|string $category, array $query = []): array
    {
        return $this->client->get(sprintf('categories/%s', $category), $query);
    }
}
