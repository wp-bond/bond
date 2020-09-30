<?php

namespace Bond;

use Bond\Support\FluentList;
use Bond\Utils\Cast;

class Posts extends FluentList
{
    public function set(array $posts)
    {
        $this->items = [];
        foreach ($posts as $post) {
            if ($post = Cast::post($post)) {
                $this->items[] = $post;
            }
        }
    }

    public function add($post, $index = -1)
    {
        if ($post = Cast::post($post)) {
            array_splice($this->items, $index, 0, [$post]);
        }
    }

    public function addMany($posts, $index = -1)
    {
        $all = [];
        foreach ($posts as $post) {
            if ($post = Cast::post($post)) {
                $all[] = $post;
            }
        }
        array_splice($this->items, $index, 0, $all);
    }

    public function ids(): array
    {
        return array_column($this->items, 'ID');
    }
}
