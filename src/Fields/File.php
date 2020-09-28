<?php

namespace Bond\Fields;

use Bond\Fields\Properties\HasReturnFormat;

/**
 *
 */
class File extends Field
{
    protected string $type = 'file';
    public string $mime_types = 'pdf,zip';
    use HasReturnFormat;
}
