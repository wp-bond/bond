<?php

namespace Bond\Fields\Acf;

// use Bond\Fields\Acf\Properties\HasReturnFormatChoices;

/**
 *
 */
class SelectField extends Field
{
    protected string $type = 'select';
    public string $return_format = 'value';


    public function choices(array $choices): self
    {
        $this->choices = $choices;
        return $this;
    }

    public function allowNull(bool $active = true): self
    {
        $this->allow_null = $active;
        return $this;
    }

    public function multiple(bool $active = true): self
    {
        $this->multiple = $active;
        return $this;
    }

    public function ui(bool $active = true): self
    {
        $this->ui = $active;
        return $this;
    }

    public function ajax(bool $active = true): self
    {
        $this->ajax = $active;
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
