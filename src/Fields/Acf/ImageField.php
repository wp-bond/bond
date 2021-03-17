<?php

namespace Bond\Fields\Acf;

class ImageField extends Field
{
    protected string $type = 'image';
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

    public function previewSize(string $size): self
    {
        $this->preview_size = $size;
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

    public function libraryAll(): self
    {
        $this->library = 'all';
        return $this;
    }

    public function libraryPost(): self
    {
        $this->library = 'uploadedTo';
        return $this;
    }

    public function minWidth(int $width): self
    {
        $this->min_width = $width;
        return $this;
    }

    public function minHeight(int $height): self
    {
        $this->min_height = $height;
        return $this;
    }

    public function minSize(int $mb): self
    {
        $this->min_size = $mb;
        return $this;
    }

    public function maxWidth(int $width): self
    {
        $this->max_width = $width;
        return $this;
    }

    public function maxHeight(int $height): self
    {
        $this->max_height = $height;
        return $this;
    }

    public function maxSize(int $mb): self
    {
        $this->max_size = $mb;
        return $this;
    }
}
