<?php

namespace Bond\Fields\Acf;

class UrlField extends Field
{
    protected string $type = 'url';

    public function placeholder(string $text): self
    {
        $this->placeholder = $text;
        return $this;
    }
}
