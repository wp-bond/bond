<?php

namespace Bond\Support;


use Bond\Settings\Languages;
use IteratorAggregate;
use ArrayAccess;
use JsonSerializable;
use Serializable;
use Countable;
use ArrayIterator;
use Bond\Utils\Arr;
use Bond\Utils\Cast;
use Bond\Utils\Obj;
use Bond\Utils\Str;
use InvalidArgumentException;

// would be lovable here methods like "only" to only retrieve the needed values
// of course needs some thoughts so we create all needed methods in advance and don't change afterwards


class Fluent implements
    ArrayAccess,
    Countable,
    IteratorAggregate,
    Serializable,
    JsonSerializable
{

    public function __construct($values = null)
    {
        $this->add($values);
    }



    public function add($values): self
    {
        if (!empty($values)) {

            if (!is_array($values) && !is_object($values)) {
                throw new InvalidArgumentException('Only add arrays or objects to Fluent');
            }

            foreach ($values as $key => $value) {
                if (isset($this->{$key}) && $this->{$key} instanceof Fluent) {
                    $this->{$key}->add($value);
                } else {
                    $this->{$key} = Cast::fluent($value);
                }
            }
        }
        return $this;
    }

    public function all(): array
    {
        return Obj::vars($this);
    }

    public function toArray(): array
    {
        return Obj::toArray($this->all(), true);
    }

    public function toJson($options = 0, $depth = 512): string
    {
        return json_encode($this->toArray(), $options, $depth);
    }

    public function get($key, string $language_code = null)
    {
        if (!$language_code) {
            return $this->__get($key);
        }

        if (is_null($key)) {
            return null;
        }

        if (strpos($key, '.') === false) {
            $key .= Languages::fieldsSuffix($language_code);
            return isset($this->{$key}) ? $this->{$key} : null;
        }

        return $this->getByDot($key, $language_code);
    }

    public function __get($key)
    {
        if (is_null($key)) {
            return null;
        }

        if (isset($this->{$key})) {
            return $this->{$key};
        }

        if (strpos($key, '.') === false) {
            if (Languages::isMultilanguage()) {
                $key .= Languages::fieldsSuffix();
                if (isset($this->{$key})) {
                    return $this->{$key};
                }
            }
            return null;
        }

        return $this->getByDot($key);
    }

    private function getByDot($key, string $language_code = null)
    {
        $target = $this;
        $keys = explode('.', $key);
        $n = count($keys);

        for ($i = 0; $i < $n; $i++) {
            $key = $keys[$i];

            if (Arr::accessible($target) && isset($target[$key])) {
                $target = $target[$key];

                // let the target handle the remainder keys
                if ($target instanceof Fluent && $i + 1 < $n) {
                    return $target->get(
                        implode('.', array_slice($keys, $i + 1)),
                        $language_code
                    );
                }
            } else {
                return null;
            }
        }
        return $target;
    }


    public function __set($key, $value)
    {
        $this->{$key} = Cast::fluent($value);
    }

    public function __unset($key)
    {
        $this->{$key} = null;
    }

    public function offsetGet($key)
    {
        return $this->__get($key);
    }

    public function offsetSet($key, $value)
    {
        $this->__set($key, $value);
    }

    public function offsetUnset($key)
    {
        $this->__unset($key);
    }

    public function offsetExists($key): bool
    {
        if (isset($this->{$key})) {
            return true;
        }
        if (Languages::isMultilanguage()) {
            $key .= Languages::fieldsSuffix();
            return isset($this->{$key});
        }
        return false;
    }

    /**
     * Return properties count. Does not skip null properties.
     *
     * @return int
     */
    public function count(): int
    {
        return count(array_keys($this->all()));
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->all());
    }

    public function localize(): self
    {
        if (!Languages::isMultilanguage()) {
            return $this;
        }
        $this->localizeProperties($this, Languages::fieldsSuffix());
        return $this;
    }

    private function localizeProperties($target, $suffix)
    {
        foreach ($target as $key => $value) {

            // recurse
            if (
                is_array($value)
                || $value instanceof Fluent
            ) {
                $target[$key] = $this->localizeProperties($value, $suffix);
                continue;
            }

            // set value, if not set yet
            if (Str::endsWith($key, $suffix)) {
                $unlocalized_key = substr($key, 0, -strlen($suffix));

                if (!isset($target->{$unlocalized_key})) {
                    $target->{$unlocalized_key} = $value;
                }
            }
        }
        return $target;
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
            $values = unserialize($serialized);
            $this->add($values);
        } else {
            throw new \InvalidArgumentException('Invalid serialized data type.');
        }
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
}
