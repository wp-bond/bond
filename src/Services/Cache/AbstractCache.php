<?php

namespace Bond\Services\Cache;

use Bond\Utils\Str;

// TODO implement Service interface too and update the enabled and config methods
// clean Config.php afterwards

abstract class AbstractCache implements CacheInterface
{
    protected int $ttl = 0;
    protected bool $enabled = true;

    public function __construct()
    {
        // disable initially if WP CLI
        if (app()->isCli()) {
            $this->enabled(false);
        }

        // set TTL
        $this->ttl(app()->isDevelopment() ? 0 : 60);

        // clear cache on switch theme
        \add_action('switch_theme', [$this, 'clear']);
    }

    public function enabled(?bool $value = null): bool
    {
        if ($value !== null) {
            $this->enabled = $value;
        }
        return $this->enabled;
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
