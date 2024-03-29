<?php

namespace Bond;

use Bond\Support\Fluent;
use Bond\Utils\Cast;
use Bond\Utils\Link;
use Bond\Utils\Query;
use WP_Term;

class Term extends Fluent
{
    public int $term_id;
    public string $taxonomy;
    public string $slug;


    // TODO move cache logic into Cast

    public function __construct($values = null, bool $skip_cache = false)
    {
        if (!$skip_cache && cache()->isEnabled()) {
            $this->initFromCache($values);
        } else {
            $this->init($values);
        }
    }

    protected function initFromCache($values)
    {
        if ($id = Cast::termId($values)) {

            $has_initted = false;

            $cached = $this->add(cache()->remember(
                'bond/terms/' . $id,
                function () use ($values, &$has_initted) {
                    $this->init($values);
                    $has_initted = true;
                    return $this->toArray();
                }
            ));
            if (!$has_initted) {
                $this->add($cached);
            }
        } else {
            $this->init($values);
        }
    }

    protected function init($values)
    {
        if (empty($values)) {
            return;
        }

        // if is numeric we'll fetch the WP_Term and load fields
        // if is WP_Term we'll just add it and load fields
        if (is_numeric($values) || $values instanceof WP_Term) {
            $term = Cast::wpTerm($values);
            if ($term) {
                $this->add($term);
                $this->loadFields();
            }
            return;
        }

        // if is string we'll try to find by slug and load fields
        if (is_string($values)) {
            if (isset($this->taxonomy)) {
                $term = Query::wpTermBySlug(
                    $values,
                    $this->taxonomy
                );
                if ($term) {
                    $this->add($term);
                    $this->loadFields();
                }
            }
            return;
        }

        // otherwise (object or array) are honored as the full value to added WITHOUT loading fields
        $this->add($values);
    }

    public function loadFields()
    {
        $this->add($this->getFields());
    }

    public function getFields(): ?array
    {
        if (
            isset($this->term_id)
            && isset($this->taxonomy)
            && app()->hasAcf()
        ) {
            return \get_fields($this->taxonomy . '_' . $this->term_id) ?: null;
        }
        return null;
    }

    public function isMultilanguage(): bool
    {
        return app()->multilanguage()->is($this);
    }

    public function taxonomyName(
        bool $singular = false,
        string $language = null
    ): string {
        return Query::taxonomyName($this->taxonomy, $singular, $language);
    }

    public function name(string $language = null): string
    {
        if ($this->isMultilanguage()) {
            return $this->get('name', $language) ?: $this->name;
        }
        return $this->name;
    }

    public function slug(string $language = null): string
    {
        if (!isset($this->slug)) {
            return '';
        }
        if ($this->isMultilanguage()) {
            return $this->get('slug', $language) ?: $this->slug;
        }
        return $this->slug;
    }

    public function link(string $language = null): string
    {
        return Link::forTerms($this, $language);
    }

    public function isEmpty(): bool
    {
        return empty($this->term_id)
            || empty($this->taxonomy)
            || parent::isEmpty();
    }
}
