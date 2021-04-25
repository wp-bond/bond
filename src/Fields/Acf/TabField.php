<?php

namespace Bond\Fields\Acf;

class TabField extends Field
{
    protected string $type = 'tab';
    public string $placement = 'top';


    // public function placementTop(): self
    // {
    //     $this->layout = 'table';
    //     return $this;
    // }

    public function endpoint(bool $endpoint = true): self
    {
        $this->endpoint = $endpoint;
        return $this;
    }
}
