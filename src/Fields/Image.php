<?php

namespace Bond\Fields;

/**
 * @method self previewSize(string $size)
 * @method self returnFormat(string $type)
 */
class Image extends Field
{
    protected string $type = 'image';
    public string $mime_types = 'jpg,jpeg,png,gif';
    public string $return_format = 'id';
}
