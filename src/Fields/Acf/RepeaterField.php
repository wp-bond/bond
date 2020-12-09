<?php

namespace Bond\Fields\Acf;

use Bond\Fields\Acf\Properties\HasSubFields;

/**
 *
 */
class RepeaterField extends Field
{
    use HasSubFields;

    protected string $type = 'repeater';
    protected array $sub_fields = [];

    protected function addField(Field $field): self
    {
        $this->sub_fields[] = $field;
        return $this;
    }
}
