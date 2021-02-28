<?php

namespace Bond\Fields\Acf;

/**
 *
 */
class PostObjectField extends Field
{
    protected string $type = 'post_object';
    public bool $ui = true;
    public string $return_format = 'id';


    public function returnId(): self
    {
        $this->return_format = 'id';
        return $this;
    }

    public function returnObject(): self
    {
        $this->return_format = 'object';
        return $this;
    }

    // array | string
    public function postType($post_types): self
    {
        $this->post_type = (array) $post_types;
        return $this;
    }

    // array | string
    public function taxonomy($taxonomies): self
    {
        $this->taxonomy = (array) $taxonomies;
        return $this;
    }

    public function allowNull(bool $active = true): self
    {
        $this->allow_null = $active;
        return $this;
    }

    public function multiple(bool $active = true): self
    {
        $this->multiple = $active;
        return $this;
    }
}
