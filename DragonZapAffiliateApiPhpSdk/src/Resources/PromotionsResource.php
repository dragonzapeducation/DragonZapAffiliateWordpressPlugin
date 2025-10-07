<?php

namespace DragonZap\AffiliateApi\Resources;

use DragonZap\AffiliateApi\Client;

class PromotionsResource
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * List available promotions.
     *
     * @param array<string, scalar|array<array-key, scalar>> $query
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        return $this->client->get('promotions', $query);
    }
}
