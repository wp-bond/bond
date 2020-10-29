<?php

namespace Bond\Fields\Acf;

use Bond\Fields\Acf\Properties\HasReturnFormatFiles;

/**
 * @method self previewSize(string $size)
 */
class Image extends Field
{
    protected string $type = 'image';
    public string $mime_types = 'jpg,jpeg,png,gif';
    public string $return_format = 'id';

    use HasReturnFormatFiles;
}
