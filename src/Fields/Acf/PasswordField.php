<?php

namespace Bond\Fields\Acf;

/**
 * @method self prepend(string $string)
 * @method self append(string $string)
 */
class PasswordField extends Field
{
    protected string $type = 'password';
}
