<?php

namespace Bond\Fields\Acf;

use Bond\Fields\Acf\Properties\HasReturnFormatFiles;

/**
 *
 */
class Gallery extends Field
{
    protected string $type = 'gallery';
    public string $mime_types = 'jpg,jpeg,png,gif';
    public string $return_format = 'id';

    use HasReturnFormatFiles;
}