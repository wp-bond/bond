<?php

namespace Bond\Fields\Acf;

class NumberField extends Field
{
    protected string $type = 'number';

    public function step(float $step): self
    {
        $this->step = $step;
        return $this;
    }

    public function min(float $min): self
    {
        $this->min = $min;
        return $this;
    }

    public function max(float $max): self
    {
        $this->max = $max;
        return $this;
    }

    public function prepend(string $label): self
    {
        $this->prepend = $label;
        return $this;
    }

    public function append(string $label): self
    {
        $this->append = $label;
        return $this;
    }

    public function placeholder(string $text): self
    {
        $this->placeholder = $text;
        return $this;
    }
}
