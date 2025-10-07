<?php

namespace DragonZap\AffiliateApi\Resources;

use DragonZap\AffiliateApi\Client;

class CategoriesResource
{
    public function __construct(private readonly Client $client)
    {
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
