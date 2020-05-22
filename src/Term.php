<?php

namespace Bond;

use Bond\Support\Fluent;
use Bond\Utils\Cache;
use Bond\Utils\Cast;
use Bond\Utils\Link;
use Bond\Settings\Languages;

class Term extends Fluent
{
    public int $term_id;
    public string $taxonomy;

    public function __construct($values = null, bool $skip_cache = false)
    {
        if (!$skip_cache && config('cache.terms')) {
            $this->initFromCache($values);
        } else {
            $this->init($values);
        }
    }

    protected function initFromCache($values)
    {
        if ($id = Cast::termId($values)) {

            $has_initted = false;

            $res = $this->add(Cache::json(
                'bond/terms/' . $id,
                config('cache.terms_ttl') ?? 60 * 10,

                function () use ($values, &$has_initted) {
                    $this->init($values);
                    $has_initted = true;
                    return $this->toArray();
                }
            ));
            if (!$has_initted) {
                $this->add($res);
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

        // add the WP term
        $this->add(Cast::wpTerm($values));

        // Load fields
        $this->loadFields();
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

    public function slug(string $language_code = null): string
    {
        if (Languages::isMultilanguage()) {
            return $this->get('slug', $language_code) ?: $this->slug;
        }
        return $this->slug;
    }

    public function link(string $language_code = null): string
    {
        return Link::forTerms($this, $language_code);
    }
}
