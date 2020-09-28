<?php

namespace Bond\Fields;

/**
 * @method self append(string $string)
 */
class Text extends Field
{
    protected string $type = 'text';

    public function placeholder(string $text): self
    {
        $this->placeholder = $text;
        return $this;
    }

    public function maxLength(int $chars): self
    {
        $this->maxlength = $chars;
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
}
