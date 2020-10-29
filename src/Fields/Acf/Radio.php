<?php

namespace Bond\Fields\Acf;

// use Bond\Fields\Acf\Properties\HasReturnFormatChoices;

/**
 *
 */
class Radio extends Field
{
    protected string $type = 'radio';
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

    public function otherChoice(bool $active = true): self
    {
        $this->other_choice = $active;
        return $this;
    }

    public function saveOtherChoice(bool $active = true): self
    {
        $this->save_other_choice = $active;
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
