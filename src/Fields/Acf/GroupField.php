<?php

namespace Bond\Fields\Acf;

use Bond\Fields\Acf\Properties\HasSubFields;

/**
 *
 */
class GroupField extends Field
{
    use HasSubFields;

    protected string $type = 'group';
    protected array $sub_fields = [];

    protected function addField(Field $field): self
    {
        $this->sub_fields[] = $field;
        return $this;
    }
}
