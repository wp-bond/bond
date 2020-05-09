<?php

namespace Bond\Fields;

/**
 * @method self prepend(string $string)
 * @method self append(string $string)
 */
class Password extends Field
{
    protected string $type = 'password';
}
