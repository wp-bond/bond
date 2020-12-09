<?php

namespace Bond\Fields\Acf;

use Bond\Fields\Acf\Properties\HasSubFields;

/**
 * @method self display(string $display)
 * @method self min(int $min)
 * @method self max(int $max)
 */
class FlexibleContentLayout extends Field
{
    use HasSubFields;

    // protected string $type = 'layout'; // there is no field type
    protected array $sub_fields = [];

    protected function addField(Field $field): self
    {
        $this->sub_fields[] = $field;
        return $this;
    }
}
