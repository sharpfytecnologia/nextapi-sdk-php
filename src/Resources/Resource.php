<?php

declare(strict_types=1);

namespace Sharpfy\NextApi\Resources;

use Sharpfy\NextApi\Input;

abstract class Resource
{
    public function __construct(protected readonly \Sharpfy\NextApi\NextApiClient $client)
    {
    }

    protected function get(string $path): mixed
    {
        return $this->client->request('GET', $path);
    }

    protected function post(string $path, array $body): mixed
    {
        return $this->client->request('POST', $path, $body);
    }
}
