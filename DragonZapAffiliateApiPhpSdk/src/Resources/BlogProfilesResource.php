<?php

namespace DragonZap\AffiliateApi\Resources;

use DragonZap\AffiliateApi\Client;

class BlogProfilesResource
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * List blog profiles for the affiliate.
     *
     * @return array<string, mixed>
     */
    public function list(): array
    {
        return $this->client->get('blog-profiles');
    }

    /**
     * Create a new blog profile.
     *
     * @param array{name:string,identifier:string} $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        return $this->client->post('blog-profiles', $payload);
    }
}
