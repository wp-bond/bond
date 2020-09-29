<?php

namespace Bond\Fields;

use Bond\Fields\Properties\HasReturnFormatFiles;

/**
 *
 */
class File extends Field
{
    protected string $type = 'file';
    public string $mime_types = 'pdf,zip';
    public string $return_format = 'id';

    use HasReturnFormatFiles;
}
