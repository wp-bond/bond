<?php

namespace Bond\Fields\Acf;

/**
 * @method self display(string $display)
 * @method self min(int $min)
 * @method self max(int $max)
 */
class Layout extends Field
{
    use AllFields;

    // protected string $type = 'layout'; // there is no field type
    protected array $sub_fields = [];

    protected function addField(Field $field): self
    {
        $this->sub_fields[] = $field;
        return $this;
    }
}
