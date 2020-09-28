<?php

namespace Bond\Fields;


use Bond\Settings\Languages;
use ArrayAccess;
use Bond\Utils\Obj;
use Bond\Utils\Str;

// TODO add params to each field
// https://www.advancedcustomfields.com/resources/register-fields-via-php/

// Idea, maybe fluent conditionals ->conditional(or()->and(), or());

/**

 */
class Field implements ArrayAccess
{
    protected string $type;
    private bool $is_multilanguage = false;
    private array $multilanguage_options;

    public function __construct(string $name, array $settings = [])
    {
        $settings['name'] = Str::az($name);

        foreach ($settings as $key => $value) {
            $this->{(string) $key} = $value;
        }
    }

    public function label(string $name): self
    {
        $this->label = $name;
        return $this;
    }

    public function instructions(string $instructions): self
    {
        $this->instructions = $instructions;
        return $this;
    }

    public function multilanguage(array $options = []): self
    {
        $this->is_multilanguage = true;
        $this->multilanguage_options = $options;
        return $this;
    }

    public function isMultilanguage(): bool
    {
        return $this->is_multilanguage;
    }

    public function wrapWidth(int $percentage): self
    {
        if (!isset($this->wrapper)) {
            $this->wrapper = [];
        }
        $this->wrapper['width'] = $percentage;
        return $this;
    }

    public function wrapClass(string $class): self
    {
        if (!isset($this->wrapper)) {
            $this->wrapper = [];
        }
        $this->wrapper['class'] = $class;
        return $this;
    }

    public function wrapId(string $id): self
    {
        if (!isset($this->wrapper)) {
            $this->wrapper = [];
        }
        $this->wrapper['id'] = $id;
        return $this;
    }

    public function defaultValue($value): self
    {
        $this->default_value = $value;
        return $this;
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;
        return $this;
    }

    public function toArray(): array
    {
        $values = Obj::toArray($this, true);
        if (isset($this->type)) {
            $values['type'] = $this->type;
        }

        // recurse on sub fields
        foreach (['sub_fields', 'layouts'] as $subs) {
            if (isset($this->{$subs})) {
                $fields = [];
                foreach ($this->{$subs} as $field) {

                    if ($field->isMultilanguage()) {
                        $fields = array_merge(
                            $fields,
                            $field->toArrayMultilanguage()
                        );
                    } else {
                        $fields[] = $field->toArray();
                    }
                }
                $values[$subs] = $fields;
            }
        }

        return $values;
    }

    public function toArrayMultilanguage(): array
    {
        $field = $this->toArray();

        $no_label = !empty($this->multilanguage_options['no_label']);
        $skip_default = !empty($this->multilanguage_options['skip_default']);
        $instructions_only_on_first = !empty($this->multilanguage_options['instructions_only_on_first']);

        $fields = [];

        foreach (Languages::codes() as $code) {

            if ($skip_default && Languages::isDefault($code)) {
                continue;
            }

            $suffix = Languages::fieldsSuffix($code);

            $f = $field;
            if (isset($f['key'])) {
                $f['key'] .= $suffix;
            }
            if (isset($f['name'])) {
                $f['name'] .= $suffix;
            }

            if (!$no_label) {
                if (!isset($f['label'])) {
                    $f['label'] = '';
                } else {
                    $f['label'] .= ' ';
                }
                $f['label'] .= Languages::fieldsLabel($code);
            }

            // go into conditional as well
            if (
                !empty($f['i18n_conditional'])
                && !empty($f['conditional_logic'])
            ) {

                $conditional = [];
                foreach ($f['conditional_logic'] as $ors) {
                    $_ands = [];
                    foreach ($ors as $and) {
                        $and['field'] .=  $suffix;
                        $_ands[] = $and;
                    }
                    $conditional[] = $_ands;
                }
                $f['conditional_logic'] = $conditional;
            }


            $fields[] = $f;

            if ($instructions_only_on_first) {
                unset($field['instructions']);
            }
        }

        return $fields;
    }


    public function __call(string $name, array $arguments): self
    {
        $this->{Str::snake($name)} = $arguments[0] ?? null;
        return $this;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        return isset($this->{$key}) ? $this->{$key} : null;
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        $this->{$key} = $value;
    }

    /**
     * @param string $key
     */
    public function __unset($key)
    {
        $this->{$key} = null;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->{$key};
    }

    /**
     * @param string $key
     * @param mixed $value
     *
     * @throws \RuntimeException if trying to set values as indexed arrays at root level, i.e., $item[0] = 'myvalue';
     */
    public function offsetSet($key, $value)
    {
        if (is_null($key) || is_numeric($key)) {
            throw new \RuntimeException('Indexed arrays not allowed at the root of ' . get_class($this) . ' objects.');
        }

        $this->{(string) $key} = $value;
    }

    /**
     * @param string $key
     */
    public function offsetUnset($key)
    {
        $this->{$key} = null;
    }

    /**
     * @param string $key
     * @return boolean
     */
    public function offsetExists($key)
    {
        return isset($this->{$key});
    }
}
