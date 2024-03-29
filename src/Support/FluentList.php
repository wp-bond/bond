<?php

namespace Bond\Support;

use IteratorAggregate;
use ArrayAccess;
use JsonSerializable;
use Countable;
use ArrayIterator;
use Bond\Settings\Language;
use Bond\Utils\Arr;
use Bond\Utils\Cast;
use Bond\Utils\Obj;

// TODO LATER give a new look into ArrayObject
// at least here we miss many methods to work as a array like chunks, slice, etc
// if needed https://github.com/illuminate/collections/blob/master/Collection.php

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

    public function set(array $items): self
    {
        $this->items = [];
        return $this->addMany($items);
    }

    public function add($item, ?int $index = null): self
    {
        // TODO here we could enforce Fluent or FluentList always...
        $item = Cast::fluent($item);

        if ($index === null) {
            $this->items[] = $item;
        } else {
            array_splice($this->items, $index, 0, [$item]);
        }
        return $this;
    }

    public function addMany($items, ?int $index = null): self
    {
        if (is_iterable($items)) {
            foreach ($items as $item) {
                $this->add($item, $index);
                if ($index !== null) {
                    $index++;
                }
            }
        }
        return $this;
    }

    public function removeIndex(int $index): self
    {
        if ($index >= 0 && $index < count($this->items)) {
            array_splice($this->items, $index, 1);
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

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->items[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
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
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __serialize(): array
    {
        return $this->all();
    }

    public function __unserialize(array $items): void
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

    public function sort(int $flags = SORT_REGULAR): self
    {
        sort($this->items, $flags);
        return $this;
    }

    public function usort(callable $callback): self
    {
        usort($this->items, $callback);
        return $this;
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

    public function mapKeys(callable $f): self
    {
        $this->items = Arr::mapKeys($f, $this->items);
        return $this;
    }

    public function camelKeys(): self
    {
        $this->items = Arr::camelKeys($this->items);
        return $this;
    }

    public function snakeKeys(): self
    {
        $this->items = Arr::snakeKeys($this->items);
        return $this;
    }

    public function shuffle(): self
    {
        shuffle($this->items);
        return $this;
    }

    public function unshift($item): self
    {
        return $this->add($item, 0);
    }

    public function limit(int $length): self
    {
        array_splice($this->items, $length);
        return $this;
    }

    public function localized(): self
    {
        return Obj::localize($this, Language::getCurrent());
    }

    public function column($column_key, $index_key = null): array
    {
        return array_column($this->items, $column_key, $index_key);
    }

    // TODO maybe should return the result instead of changing the source data, just like column above
    // if so, change on profilePosters
    public function slice(int $offset, int $length = null): self
    {
        $this->items = array_slice($this->items, $offset, $length);
        return $this;
    }

    // TODO maybe should return the result instead of changing the source data, just like column above
    public function filter(callable $callback = null): self
    {
        $this->items = array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH);
        return $this;
    }
}
