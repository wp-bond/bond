<?php

namespace Bond\Fields;

/**
 *
 */
class Group extends Field
{
    use AllFields;

    protected string $type = 'group';
    protected array $sub_fields = [];

    protected function addField(Field $field): self
    {
        $this->sub_fields[] = $field;
        return $this;
    }
}
