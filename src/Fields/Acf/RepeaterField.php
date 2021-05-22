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


    public function buttonLabel(
        string $label,
        string $written_language = null
    ): self {
        $this->button_label = tx($label, 'fields', null, $written_language);
        return $this;
    }

    public function min(int $min): self
    {
        $this->min = $min;
        return $this;
    }

    public function max(int $max): self
    {
        $this->max = $max;
        return $this;
    }

    public function showWhenCollapsed(string $name): self
    {
        // TODO
        // $this->collapsed =
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
