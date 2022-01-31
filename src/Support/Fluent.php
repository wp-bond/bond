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

class Fluent implements
    ArrayAccess,
    Countable,
    IteratorAggregate,
    JsonSerializable
{
    // set properties that should not be added here
    protected array $exclude;


    public function __construct($data = null)
    {
        $this->add($data);
    }

    public function add($data): self
    {
        if (!empty($data)) {

            if (is_object($data)) {
                $data = Obj::vars($data);
            }

            if (!is_array($data)) {
                throw new InvalidArgumentException('Only add arrays or objects to Fluent');
            }

            // don't let unwanted data in
            if (isset($this->exclude)) {
                $data = array_diff_key(
                    $data,
                    array_flip($this->exclude)
                );
            }

            // transfer in
            foreach ($data as $key => $value) {
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
        return count($this->keys());
    }

    public function keys(): array
    {
        return array_keys($this->all());
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

    public function get($key, string $language = null)
    {
        if (!$language) {
            return $this->__get($key);
        }

        if (is_null($key)) {
            return null;
        }

        if (strpos($key, '.') === false) {
            $key .= Language::fieldsSuffix($language);
            return isset($this->{$key}) ? $this->{$key} : null;
        }

        return $this->getByDot($key, $language);
    }

    public function __get(string $key): mixed
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

    private function getByDot(string $key, string $language = null): mixed
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
                        $language
                    );
                }
            } else {
                return null;
            }
        }
        return $target;
    }

    public function getTranslations($key): array
    {
        $t = [];
        foreach (Language::codes() as $code) {
            $t[$code] = $this->get($key, $code);
        }
        return $t;
    }

    public function localized(): self
    {
        return Obj::localize($this, Language::getCurrent());
    }

    public function __set(string $key, mixed $value): void
    {
        $this->{$key} = Cast::fluent($value);
    }

    public function __unset(string $key): void
    {
        $this->{$key} = null;
    }

    public function offsetGet(mixed $key): mixed
    {
        return $this->__get($key);
    }

    public function offsetSet(mixed $key, $value): void
    {
        $this->__set($key, $value);
    }

    public function offsetUnset(mixed $key): void
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

    public function __unserialize(array $data): void
    {
        $this->add($data);
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

    public function jsTag(string $js_var_name): string
    {
        return '<script>'
            . $js_var_name . ' = JSON.parse(' . json_encode(json_encode($this)) . ')'
            . '</script>';
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

    public function only(array $keys): static
    {
        return new static(Arr::only($this->all(), $keys));
    }
}
