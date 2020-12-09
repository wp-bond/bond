<?php

namespace Bond\Fields\Acf;

class FlexibleContentField extends Field
{
    protected string $type = 'flexible_content';
    protected array $layouts = [];

    public function layout(string $name): FlexibleContentLayout
    {
        $field = new FlexibleContentLayout($name);
        $this->addLayout($field);
        return $field;
    }

    public function addLayout(FlexibleContentLayout $field): self
    {
        $this->layouts[] = $field;
        return $this;
    }

    public function buttonLabel(string $label): self
    {
        $this->button_label = $label;
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
}
