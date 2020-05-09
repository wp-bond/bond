<?php

namespace Bond\Fields;

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
}
