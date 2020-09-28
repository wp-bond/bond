<?php

namespace Bond\Fields\Properties;

trait HasReturnFormat
{
    public string $return_format = 'id';

    public function returnFormat(string $type): self
    {
        $this->return_format = $type;
        return $this;
    }
}
