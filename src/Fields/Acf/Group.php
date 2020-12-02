<?php

namespace Bond\Fields\Acf;

/**
 *
 */
class Group extends Field
{
    use FieldsTrait;

    protected string $type = 'group';
    protected array $sub_fields = [];

    protected function addField(Field $field): self
    {
        $this->sub_fields[] = $field;
        return $this;
    }
}
