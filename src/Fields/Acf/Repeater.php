<?php

namespace Bond\Fields\Acf;

/**
 *
 */
class Repeater extends Field
{
    use AllFields;

    protected string $type = 'repeater';
    protected array $sub_fields = [];

    protected function addField(Field $field): self
    {
        $this->sub_fields[] = $field;
        return $this;
    }
}
