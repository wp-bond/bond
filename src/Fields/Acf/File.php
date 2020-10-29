<?php

namespace Bond\Fields\Acf;

use Bond\Fields\Acf\Properties\HasReturnFormatFiles;

/**
 *
 */
class File extends Field
{
    protected string $type = 'file';
    public string $mime_types = 'pdf,zip';
    public string $return_format = 'id';

    use HasReturnFormatFiles;

    public function mimeTypes($extensions): self
    {
        if (is_array($extensions)) {
            $extensions = implode(',', $extensions);
        }
        $this->mime_types = (string) $extensions;
        return $this;
    }
}
