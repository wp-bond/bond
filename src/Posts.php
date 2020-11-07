<?php

namespace Bond;

use Bond\Support\FluentList;
use Bond\Utils\Cast;

class Posts extends FluentList
{
    public function set(array $posts): self
    {
        $this->items = [];
        foreach ($posts as $post) {
            if ($post = Cast::post($post)) {
                $this->items[] = $post;
            }
        }
        return $this;
    }

    public function add($post, $index = -1): self
    {
        if ($post = Cast::post($post)) {
            array_splice($this->items, $index, 0, [$post]);
        }
        return $this;
    }

    public function addMany($posts, $index = -1): self
    {
        $all = [];
        foreach ($posts as $post) {
            if ($post = Cast::post($post)) {
                $all[] = $post;
            }
        }
        array_splice($this->items, $index, 0, $all);
        return $this;
    }

    public function ids(): array
    {
        return array_column($this->items, 'ID');
    }

    public function unique(): self
    {
        $unique = [];

        foreach ($this->items as $item) {
            if (!isset($unique[$item->ID])) {
                $unique[$item->ID] = $item;
            }
        }

        $this->items = array_values($unique);
        return $this;
    }
}
