<?php

namespace Bond\Fields\Acf;


use Bond\Settings\Language;
use ArrayAccess;
use Bond\Utils\Obj;
use Bond\Utils\Str;

// TODO add params to each field
// https://www.advancedcustomfields.com/resources/register-fields-via-php/

// Idea, maybe fluent conditionals ->conditional(or()->and(), or());

// TODO maybe transform in abstract to not allow a undefined field

/**

 */
class Field implements ArrayAccess
{
    protected string $type;
    public string $label;
    private bool $is_multilanguage = false;
    private array $multilanguage_options;

    public function __construct(string $name, array $settings = [])
    {
        $settings['name'] = Str::az($name);

        foreach ($settings as $key => $value) {
            $this->{(string) $key} = $value;
        }
    }

    public function label(
        string $name,
        string $written_language = null
    ): self {
        $this->label = tx($name, 'fields', null, $written_language);
        return $this;
    }

    public function instructions(
        string $instructions,
        string $written_language = null
    ): self {
        $this->instructions = tx($instructions, 'fields', null, $written_language);
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

    // rename to just width / class / id
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

    public function default($value): self
    {
        return $this->defaultValue($value);
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;
        return $this;
    }

    // public function conditionalLogic(array $logic): self
    // {
    //     $this->conditional_logic = self::formatConditionalLogic($logic);
    //     return $this;
    // }

    // private static function formatConditionalLogic(array $logic): array
    // {
    //     $result = [];

    //     foreach ((array) $logic as $location => $options) {
    //     }

    //     return $result;
    // }

    public function toArray(): array
    {
        $values = Obj::toArray($this, true);

        if (isset($this->type)) {
            $values['type'] = $this->type;
        }
        if (!isset($values['label'])) {
            $values['label'] = tx(Str::title($values['name'], true), 'fields');
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


        foreach (Language::codes() as $code) {

            if ($skip_default && Language::isDefault($code)) {
                continue;
            }

            $suffix = Language::fieldsSuffix($code);

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
                $f['label'] .= Language::fieldsLabel($code);
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

    public function __get(string $key): mixed
    {
        return isset($this->{$key}) ? $this->{$key} : null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->{$key} = $value;
    }


    public function __unset(string $key): void
    {
        $this->{$key} = null;
    }

    public function offsetGet(mixed $key): mixed
    {
        return $this->{$key};
    }

    /**
     *
     * @throws \RuntimeException if trying to set values as indexed arrays at root level, i.e., $item[0] = 'myvalue';
     */
    public function offsetSet(mixed $key, mixed $value): void
    {
        if (is_null($key) || is_numeric($key)) {
            throw new \RuntimeException('Indexed arrays not allowed at the root of ' . get_class($this) . ' objects.');
        }

        $this->{(string) $key} = $value;
    }

    public function offsetUnset(mixed $key): void
    {
        $this->{$key} = null;
    }

    public function offsetExists(mixed $key): bool
    {
        return isset($this->{$key});
    }
}
