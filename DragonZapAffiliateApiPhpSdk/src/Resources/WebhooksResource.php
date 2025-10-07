<?php

namespace DragonZap\AffiliateApi\Resources;

use DragonZap\AffiliateApi\Client;

class WebhooksResource
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * List configured webhooks.
     *
     * @return array<string, mixed>
     */
    public function list(): array
    {
        return $this->client->get('webhooks');
    }

    /**
     * Create a webhook.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        return $this->client->post('webhooks', $payload);
    }

    /**
     * Delete a webhook by ID.
     *
     * @param int|string $webhookId
     * @return array<string, mixed>
     */
    public function delete(int|string $webhookId): array
    {
        return $this->client->delete(sprintf('webhooks/%s', $webhookId));
    }
}
