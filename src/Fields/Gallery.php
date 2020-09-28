<?php

namespace Bond\Fields;

use Bond\Fields\Properties\HasReturnFormat;

/**
 *
 */
class Gallery extends Field
{
    use HasReturnFormat;

    protected string $type = 'gallery';
    public string $mime_types = 'jpg,jpeg,png,gif';
}
