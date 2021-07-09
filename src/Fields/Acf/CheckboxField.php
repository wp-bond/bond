<?php

namespace Bond\Fields\Acf;

class CheckboxField extends Field
{
    protected string $type = 'checkbox';
    public string $return_format = 'value';


    public function choices(array $choices): self
    {
        $this->choices = $choices;
        return $this;
    }

    public function allowCustom(bool $active = true): self
    {
        $this->allow_custom = $active;
        return $this;
    }

    public function selectAllToggle(bool $active = true): self
    {
        $this->toggle = $active;
        return $this;
    }

    public function layoutVertical(): self
    {
        $this->layout = 'vertical';
        return $this;
    }

    public function layoutHorizontal(): self
    {
        $this->layout = 'horizontal';
        return $this;
    }

    public function returnValue(): self
    {
        $this->return_format = 'value';
        return $this;
    }

    public function returnLabel(): self
    {
        $this->return_format = 'label';
        return $this;
    }

    public function returnArray(): self
    {
        $this->return_format = 'array';
        return $this;
    }
}
