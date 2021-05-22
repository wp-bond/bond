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
}
