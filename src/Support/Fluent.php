<?php

namespace Bond\Support;


use Bond\Settings\Language;
use IteratorAggregate;
use ArrayAccess;
use JsonSerializable;
use Countable;
use ArrayIterator;
use Bond\Utils\Arr;
use Bond\Utils\Cast;
use Bond\Utils\Obj;
use Bond\Utils\Str;
use InvalidArgumentException;

// would be lovable here methods like "only" to only retrieve the needed values
// of course needs some thoughts so we create all needed methods in advance and don't change afterwards

// TODO replaceAll method?
// public function replaceAll(array $data)
//     {
//         $this->view_data = new Fluent($data);
//     }


class Fluent implements
    ArrayAccess,
    Countable,
    IteratorAggregate,
    JsonSerializable
{
    // set properties that should not be added here
    protected array $exclude;


    public function __construct($values = null)
    {
        $this->add($values);
    }

    public function add($values): self
    {
        if (!empty($values)) {

            if (is_object($values)) {
                $values = Obj::vars($values);
            }

            if (!is_array($values)) {
                throw new InvalidArgumentException('Only add arrays or objects to Fluent');
            }

            // don't let unwanted values in
            if (isset($this->exclude)) {
                $values = array_diff_key(
                    $values,
                    array_flip($this->exclude)
                );
            }

            // transfer in

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

    public function values(string $for = ''): Fluent
    {
        return new Fluent($for ? null : $this->all());
    }

    public function all(): array
    {
        return Obj::vars($this);
    }

    /**
     * Return properties count. Does not skip null properties.
     */
    public function count(): int
    {
        return count(array_keys($this->all()));
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function empty(): self
    {
        foreach (array_keys($this->all()) as $key) {
            unset($this->{$key});
        }
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

    public function get($key, string $language_code = null)
    {
        if (!$language_code) {
            return $this->__get($key);
        }

        if (is_null($key)) {
            return null;
        }

        if (strpos($key, '.') === false) {
            $key .= Language::fieldsSuffix($language_code);
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
            if (Language::isMultilanguage()) {
                $key .= Language::fieldsSuffix();
                if (isset($this->{$key})) {
                    return $this->{$key};
                }
            }
            return null;
        }

        return $this->getByDot($key);
    }

    public function getTranslations($key): array
    {
        $t = [];
        foreach (Language::codes() as $code) {
            $t[$code] = $this->get($key, $code);
        }
        return $t;
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
        unset($this->{$key});
    }

    public function offsetExists($key): bool
    {
        if (isset($this->{$key})) {
            return true;
        }
        if (Language::isMultilanguage()) {
            $key .= Language::fieldsSuffix();
            return isset($this->{$key});
        }
        return false;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->all());
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

    public function __unserialize(array $values)
    {
        $this->add($values);
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

    /**
     * Deep clone.
     */
    // TODO, some test with clone to understand better
    // public function __clone()
    // {
    //     foreach ($this as $key => $value) {
    //         $this[$key] = Obj::clone($value);
    //     }
    // }

    public function mapKeys(callable $f): self
    {
        foreach ($this as $key => $value) {
            $k = $f($key);

            if (is_array($value)) {
                $this[$k] = Arr::mapKeys($f, $value);
            } elseif ($value instanceof Fluent) {
                $this[$k] = $value->mapKeys($f);
            } else {
                $this[$k] = $value;
            }

            if ($k !== $key) {
                unset($this[$key]);
            }
        }
        return $this;
    }

    public function camelKeys(): self
    {
        return $this->mapKeys([Str::class, 'camel']);
    }

    public function snakeKeys(): self
    {
        return $this->mapKeys([Str::class, 'snake']);
    }
}
