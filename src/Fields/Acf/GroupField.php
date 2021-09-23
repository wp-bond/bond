<?php

namespace Bond\Fields\Acf;

use Bond\Fields\Acf\Properties\HasSubFields;

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

    public function layoutTable(): self
    {
        $this->layout = 'table';
        return $this;
    }

    public function layoutBlock(): self
    {
        $this->layout = 'block';
        return $this;
    }

    public function layoutRow(): self
    {
        $this->layout = 'row';
        return $this;
    }
}
