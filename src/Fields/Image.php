<?php

namespace Bond\Fields;

use Bond\Fields\Properties\HasReturnFormatFiles;

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
