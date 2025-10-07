<?php

namespace DragonZap\AffiliateApi\Resources;

use DragonZap\AffiliateApi\Client;

class PromotionsResource
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
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
