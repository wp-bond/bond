<?php

namespace Bond;

use Bond\Support\FluentList;
use Bond\Utils\Cast;

class Posts extends FluentList
{
    public function add($post, ?int $index = null): self
    {
        if ($post = Cast::post($post)) {
            parent::add($post, $index);
        }
        return $this;
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

    public function ids(): array
    {
        return array_column($this->items, 'ID');
    }

    public function titles(string $language = null): array
    {
        $res = [];
        foreach ($this->items as $item) {
            $res[] = $item->title($language);
        }
        return $res;
    }

    public function slugs(string $language = null): array
    {
        $res = [];
        foreach ($this->items as $item) {
            $res[] = $item->slug($language);
        }
        return $res;
    }

    public function except($posts): self
    {
        $to_remove = Cast::postsIds($posts);

        $this->items = array_filter(
            $this->items,
            function ($post) use ($to_remove) {
                return !in_array($post->ID, $to_remove);
            }
        );
        return $this;
    }

    public function onlyOfTerms(int|iterable $terms): self
    {
        $ids = Cast::termsIds($terms);

        if (empty($ids)) {
            return $this;
        }

        $this->items = array_filter(
            $this->items,
            function ($post) use ($ids) {
                foreach ($post->termsIds() as $id) {
                    if (in_array($id, $ids)) {
                        return true;
                    }
                }
                return false;
            }
        );
        return $this;
    }
}
