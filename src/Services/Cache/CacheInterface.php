<?php

namespace Bond\Services\Cache;

use Bond\Services\ServiceInterface;
use DateInterval;
use DateTimeInterface;
use Psr\SimpleCache\CacheInterface as PsrInterface;

interface CacheInterface extends PsrInterface, ServiceInterface
{
    /** The default TTL in seconds */
    public function ttl(?int $seconds = null): int;

    public function remember(
        string $key,
        $value,
        null|int|DateInterval|DateTimeInterface $ttl = null
    );

    /** Helper to create a cache key with arbitrary param array */
    public function keyHash(string $key, string|array $hash): string;
}
