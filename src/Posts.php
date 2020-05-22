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
        $post = Cast::post($post);
        if ($post) {
            array_splice($this->items, $index, 0, [$post]);
        }
    }
}
