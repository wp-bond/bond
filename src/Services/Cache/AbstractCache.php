<?php

namespace Bond\Services\Cache;

use Bond\Utils\Str;

abstract class AbstractCache implements CacheInterface
{
    protected int $ttl = 0;
    protected bool $enabled = false;

    public function __construct()
    {
        // clear cache on switch theme
        \add_action('switch_theme', [$this, 'clear']);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable()
    {
        $this->enabled = true;
    }

    public function disable()
    {
        $this->enabled = false;
    }

    public function ttl(?int $seconds = null): int
    {
        if ($seconds !== null) {
            $this->ttl = $seconds;
        }
        return $this->ttl;
    }

    public function keyHash(string $key, string|array $hash): string
    {
        return $key
            . (!empty($hash)
                ? '-' . md5(Str::kebab($hash))
                : '');
    }

    protected function value($value)
    {
        return is_callable($value) ? $value() : $value;
    }
}
