<?php

namespace Bond\Fields;

/**
 *
 * @method self placeholder(string $value) Appears within the input.
 * @method self rows(int $value)
 */
class TextArea extends Field
{
    protected string $type = 'textarea';
}
