<?php

namespace Bond\Fields\Acf;

/**
 *
 */
class FileField extends Field
{
    protected string $type = 'file';
    public string $mime_types = 'pdf,zip';
    public string $return_format = 'id';

    public function returnId(): self
    {
        $this->return_format = 'id';
        return $this;
    }

    public function returnUrl(): self
    {
        $this->return_format = 'url';
        return $this;
    }

    public function returnArray(): self
    {
        $this->return_format = 'array';
        return $this;
    }

    public function mimeTypes($extensions): self
    {
        if (is_array($extensions)) {
            $extensions = implode(',', $extensions);
        }
        $this->mime_types = (string) $extensions;
        return $this;
    }
}
