<?php

namespace Bond\Fields\Acf;

class TaxonomyField extends Field
{
    protected string $type = 'taxonomy';
    public bool $load_terms = true;
    public bool $save_terms = true;
    public string $return_format = 'id';

    public function taxonomy(string $taxonomy): self
    {
        $this->taxonomy = $taxonomy;
        return $this;
    }

    public function typeCheckbox(): self
    {
        $this->field_type = 'checkbox';
        return $this;
    }

    public function typeRadio(): self
    {
        $this->field_type = 'radio';
        return $this;
    }

    public function typeSelect(): self
    {
        $this->field_type = 'select';
        return $this;
    }

    public function typeMultiSelect(): self
    {
        $this->field_type = 'multi_select';
        return $this;
    }

    // should not be needed
    // public function allowNull(bool $active = true): self
    // {
    //     $this->allow_null = $active;
    //     return $this;
    // }

    public function loadTerms(bool $active = true): self
    {
        $this->load_terms = $active;
        return $this;
    }

    public function saveTerms(bool $active = true): self
    {
        $this->save_terms = $active;
        return $this;
    }

    public function allowNewTerms(bool $active = true): self
    {
        $this->add_term = $active;
        return $this;
    }

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
}
