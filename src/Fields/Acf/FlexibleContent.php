<?php

namespace Bond\Fields\Acf;

/**
 * @method self buttonLabel(string $label)
 * @method self min(int $min)
 * @method self max(int $max)
 */
class FlexibleContent extends Field
{
    protected string $type = 'flexible_content';
    protected array $layouts = [];

    public function layout(string $name): Layout
    {
        $field = new Layout($name);
        $this->addLayout($field);
        return $field;
    }

    public function addLayout(Layout $field): self
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
