<?php

namespace Bond\Fields\Acf;

/**
 *
 * @method self placeholder(string $value) Appears within the input.
 * @method self rows(int $value)
 */
class TextAreaField extends Field
{
    protected string $type = 'textarea';


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

    public function rows(int $lines): self
    {
        $this->rows = $lines;
        return $this;
    }

    public function lineBreakNone(): self
    {
        $this->new_lines = '';
        return $this;
    }

    public function lineBreakBr(): self
    {
        $this->new_lines = 'br';
        return $this;
    }

    public function lineBreakParagraph(): self
    {
        $this->new_lines = 'wpautop';
        return $this;
    }
}
