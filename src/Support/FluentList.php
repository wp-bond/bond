<?php

namespace Bond\Support;


use IteratorAggregate;
use ArrayAccess;
use JsonSerializable;
use Serializable;
use Countable;
use ArrayIterator;
use Bond\Utils\Cast;
use Bond\Utils\Obj;

// TODO LATER give a new look into ArrayObject
// at least here we miss many methods to work as a array like chunks, slive, etc

class FluentList implements
    ArrayAccess,
    Countable,
    IteratorAggregate,
    Serializable,
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

    public function add($item, $index = -1)
    {
        array_splice($this->items, $index, 0, [Cast::fluent($item)]);
    }

    public function get(): array
    {
        return $this->items;
    }

    public function toArray(): array
    {
        return Obj::toArray($this->items, true);
    }

    public function toJson($options = 0, $depth = 512): string
    {
        return json_encode($this, $options, $depth);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
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

    /**
     * @return string
     */
    public function serialize(): string
    {
        return serialize($this->toArray());
    }

    /**
     * @param string $serialized
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function unserialize($serialized)
    {
        if (is_string($serialized)) {

            $items = unserialize($serialized);
            $this->set($items);
        } else {
            throw new \InvalidArgumentException('Invalid serialized data type.');
        }
    }

    /**
     * Convert the model to its string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toJson();
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
}
