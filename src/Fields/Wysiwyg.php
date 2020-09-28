<?php

namespace Bond\Fields;


/**
 *
 */
class Wysiwyg extends Field
{
    protected string $type = 'wysiwyg';


    public function allTabs(): self
    {
        $this->tabs = 'all';
        return $this;
    }

    public function visualTabOnly(): self
    {
        $this->tabs = 'visual';
        return $this;
    }

    public function textTabOnly(): self
    {
        $this->tabs = 'text';
        return $this;
    }

    public function toolbar(string $id): self
    {
        $this->toolbar = $id;
        return $this;
    }

    public function mediaUpload(bool $active = true): self
    {
        $this->media_upload = $active;
        return $this;
    }

    public function delayInitialization(bool $active = true): self
    {
        $this->delay = $active;
        return $this;
    }
}
