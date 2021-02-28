<?php

namespace Bond;

use Bond\Support\Fluent;
use Bond\Support\FluentList;
use Bond\Utils\Cast;

class Terms extends FluentList
{
    public function set(array $terms): self
    {
        $this->items = [];
        foreach ($terms as $term) {
            if ($term = Cast::term($term)) {
                $this->items[] = $term;
            }
        }
        return $this;
    }

    public function add($term, $index = -1): self
    {
        $term = Cast::term($term);
        if ($term) {
            array_splice($this->items, $index, 0, [$term]);
        }
        return $this;
    }

    public function addMany($terms, $index = -1): self
    {
        $all = [];
        foreach ($terms as $term) {
            if ($term = Cast::term($term)) {
                $all[] = $term;
            }
        }
        array_splice($this->items, $index, 0, $all);
        return $this;
    }

    public function ids(): array
    {
        return array_column($this->items, 'term_id');
    }

    public function unique(): self
    {
        $unique = [];

        foreach ($this->items as $item) {
            if (!isset($unique[$item->term_id])) {
                $unique[$item->term_id] = $item;
            }
        }

        $this->items = array_values($unique);
        return $this;
    }
}
