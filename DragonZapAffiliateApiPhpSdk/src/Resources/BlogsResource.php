<?php

namespace DragonZap\AffiliateApi\Resources;

use DragonZap\AffiliateApi\Client;

class BlogsResource
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Create a new blog post draft.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        return $this->client->post('blogs', $payload);
    }

    /**
     * Partially update an existing blog post using PATCH semantics.
     *
     * @param int|string $blogId
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(int|string $blogId, array $payload): array
    {
        return $this->client->patch(sprintf('blogs/%s', $blogId), $payload);
    }

    /**
     * Replace an existing blog post with a full payload using PUT semantics.
     *
     * @param int|string $blogId
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function replace(int|string $blogId, array $payload): array
    {
        return $this->client->put(sprintf('blogs/%s', $blogId), $payload);
    }
}
