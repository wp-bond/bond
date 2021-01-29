<?php

namespace Bond\Fields\Acf;

/**
 *
 */
class GalleryField extends Field
{
    protected string $type = 'gallery';
    public string $mime_types = 'jpg,jpeg,png,gif';
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
}
