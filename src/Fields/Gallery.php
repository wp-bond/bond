<?php

namespace Bond\Fields;

/**
 * @method self returnFormat(string $type)
 */
class Gallery extends Field
{
    protected string $type = 'gallery';
    public string $mime_types = 'jpg,jpeg,png,gif';
    public string $return_format = 'id';
}
