<?php

namespace Bond\Support;

use IteratorAggregate;
use ArrayAccess;
use JsonSerializable;
use Countable;
use ArrayIterator;
use Bond\Utils\Cast;
use Bond\Utils\Obj;

// TODO LATER give a new look into ArrayObject
// at least here we miss many methods to work as a array like chunks, slice, etc

class FluentList implements
    ArrayAccess,
    Countable,
    IteratorAggregate,
    JsonSerializable
{
    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->set($items);
    }

    public function set(array $items)
    {
        $this->items = [];

        foreach ($items as $item) {
            $this->items[] = Cast::fluent($item);
        }
    }

    public function add($item, ?int $index = null): self
    {
        $item = Cast::fluent($item);

        if ($index === null) {
            array_push($this->items, $item);
        } else {
            array_splice($this->items, $index, 0, [$item]);
        }
        return $this;
    }

    public function addMany($items, ?int $index = null): self
    {
        $all = [];
        foreach ($items as $item) {
            $all[] = Cast::fluent($item);
        }
        if ($index === null) {
            $this->items = array_merge($this->items, $all);
        } else {
            array_splice($this->items, $index, 0, $all);
        }
        return $this;
    }

    public function values($for = ''): array
    {
        $values = [];
        foreach ($this->all() as $item) {
            if ($item) {
                $values[] = $item->values($for);
            }
        }
        return $values;
    }

    public function all(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->all());
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function empty(): self
    {
        $this->items = [];
        return $this;
    }

    public function run(string $path): self
    {
        require $path;
        return $this;
    }


    public function toArray(): array
    {
        return Obj::toArray($this->all(), true);
    }

    public function toJson($options = 0, $depth = 512): string
    {
        return json_encode($this, $options, $depth);
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->all());
    }

    /**
     * @param string $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->items[$offset];
    }

    /**
     * @param string $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->items[$offset] = $value;
    }

    /**
     * @param string $offset
     */
    public function offsetUnset($offset)
    {
        $this->items[$offset] = null;
    }

    /**
     * @param string $offset
     * @return boolean
     */
    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * Serialize the array to JSON.
     *
     * @see http://php.net/jsonserializable.jsonserialize
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __serialize(): array
    {
        return $this->all();
    }

    public function __unserialize(array $items)
    {
        $this->set($items);
    }

    /**
     * Convert the model to its string representation.
     *
     * Rightfully empty
     */
    public function __toString(): string
    {
        return '';
    }

    public function sort(int $flags = SORT_REGULAR): bool
    {
        return sort($this->items, $flags);
    }

    public function usort(callable $callback): bool
    {
        return usort($this->items, $callback);
    }

    public function sortBy(string $key): self
    {
        // TODO maybe add param to choose the compare function
        $this->usort(function ($a, $b) use ($key) {
            return strnatcasecmp($a->{$key}, $b->{$key});
        });
        return $this;
    }

    public function reverse(): self
    {
        $this->items = array_reverse($this->items);
        return $this;
    }
}
