<?php

namespace Bond;

use Bond\Support\Fluent;
use Bond\Utils\Image;
use Bond\Utils\File;

class Attachment extends Post
{
    public string $post_type = 'attachment';


    public function link(string $lang = null): string
    {
        return Image::url($this->ID, 'original');
    }

    public function caption(): string
    {
        return Image::caption($this->ID);
    }

    public function alt(): string
    {
        return Image::alt($this->ID);
    }

    public function meta(): ?array
    {
        return File::meta($this->ID);
    }

    public function pictureTag(
        $size = 'thumbnail',
        bool $with_caption = false
    ): string {
        return Image::pictureTag(
            $this->ID,
            $size,
            $with_caption
        );
    }

    public function values(string $for = ''): Fluent
    {
        $values = new Fluent();

        switch ($for) {

            case 'archive':
            case 'search':
                $values->id = $this->ID;
                $values->imageTag = $this->pictureTag();
                break;

            default:
                $values->id = $this->ID;
                $values->imageTag = $this->pictureTag($for);
                $values->caption = $this->caption();
                break;
        }

        return $values;
    }
}
