<?php

namespace Bond;

use Bond\Support\Fluent;
use Bond\Support\FluentList;
use Bond\Utils\Cast;

class Terms extends FluentList
{
    public function set(array $terms)
    {
        $this->items = [];
        foreach ($terms as $term) {
            if ($term = Cast::term($term)) {
                $this->items[] = $term;
            }
        }
    }

    public function add($term, $index = -1)
    {
        $term = Cast::term($term);
        if ($term) {
            array_splice($this->items, $index, 0, [$term]);
        }
    }

    public function values($for = ''): Fluent
    {
        $values = [];
        foreach ($this->items as $term) {
            $values[] = $term->values($for);
        }
        return new Fluent($values);
    }
}
