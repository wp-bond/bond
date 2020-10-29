<?php

namespace Bond\Fields\Acf;

use Bond\Fields\Acf\Properties\HasReturnFormatFiles;

/**
 *
 */
class Relationship extends Field
{
    protected string $type = 'relationship';
    public array $filters = [
        'search',
        'post_type',
        'taxonomy',
    ];
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

    public function filters(array $filters): self
    {
        $this->filters = $filters;
        return $this;
    }

    public function removeFilters(): self
    {
        $this->filters = [];
        return $this;
    }

    public function removeSearchFilter(): self
    {
        $this->filters = array_diff($this->filters, ['search']);
        return $this;
    }

    public function removePostTypeFilter(): self
    {
        $this->filters = array_diff($this->filters, ['post_type']);
        return $this;
    }

    public function removeTaxonomyFilter(): self
    {
        $this->filters = array_diff($this->filters, ['taxonomy']);
        return $this;
    }

    public function elements(array $elements): self
    {
        $this->elements = $elements;
        return $this;
    }

    public function min(int $min): self
    {
        $this->min = $min;
        return $this;
    }

    public function max(int $max): self
    {
        $this->max = $max;
        return $this;
    }
}
