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

    public function postType(array $post_types): self
    {
        $this->post_type = $post_types;
        return $this;
    }

    public function taxonomy(array $taxonomies): self
    {
        $this->taxonomy = $taxonomies;
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
