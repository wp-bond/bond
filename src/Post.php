<?php

namespace Bond;

use Bond\Utils\Link;
use Bond\Support\Fluent;
use Bond\Utils\Cast;
use Bond\Utils\Query;
use WP_Post;

class Post extends Fluent
{
    public int $ID;
    public string $post_type;
    public string $post_name;
    public string $page_template;

    // public FieldManager $fields;

    // public Text $my_awesome_field;

    // MAYBE
    // here the idea is to allow any property as being a field DB data
    // and then allow a field class to take control of that data

    // $this->images would return an array of id for example
    // then each field provides its own helpers`
    // $this->fields('images')->limit(2)->pictureTag()
    // $this->fields()->image->upload($_FILES)

    // it s a split of concerns:
    // the source data, and the manager of that data




    // set properties that should not be added here
    protected array $exclude = [
        // 'post_title',
        // 'post_name',
        // 'post_mime_type',
        // 'post_author',
        // 'post_parent',
        // 'post_date',
        // 'post_date_gmt',
        // 'post_modified',
        // 'post_modified_gmt',
        // 'post_content',
        'post_content_filtered',
        // 'post_excerpt',
        // 'menu_order',
        'ping_status',
        'to_ping',
        'pinged',
        'guid',
        'comment_status',
        'comment_count',
        'post_password', // always!
        'filter',
    ];

    public function __construct($values = null)
    {
        // register all fields upfront
        // $this->my_awesome_field = new Text('my_awesome_field');
        // ISSUE the only issue is the key must be set... with duplicate code
        // MAYBE we don't even need.. right? if Vue field could suffice with random id

        // another issue is performance? as all posts would have duplicate field code! Yes, but essentially the same as MongoModel, where we would add a trait anyway with all methods

        // MAYBE we could auto initialize, altought, most of the time it wouldn't be with default options.. at least a label is needed in most cases
        // $ref = new ReflectionClass($this);
        // dd(
        //     $ref->getProperties(ReflectionProperty::IS_PUBLIC),
        //     $ref->getProperties(ReflectionProperty::IS_PUBLIC)[2]->getType()
        // );
        // TO IMPROVE performance we would just cache in static, even later with the WeakRefs in php 8


        $this->init($values);
    }

    protected function init($values)
    {
        if (empty($values)) {
            return;
        }

        // if is numeric we'll fetch the WP_Post and load fields
        // if is WP_Post we'll just add it and load fields
        if (is_numeric($values) || $values instanceof WP_Post) {
            $post = Cast::wpPost($values);
            if ($post) {
                $this->add($post);
                $this->loadFields();
            }
            return;
        }

        // if is string we'll try to find by slug and load fields
        if (is_string($values)) {

            if (isset($this->post_type)) {
                $post = Query::wpPostBySlug(
                    $values,
                    $this->post_type
                );
                if ($post) {
                    $this->add($post);
                    $this->loadFields();
                }
            }
            return;
        }

        // otherwise (object or array) are honored as the full value
        // and added WITHOUT loading fields
        $this->add($values);
    }

    public function loadFields()
    {
        $this->add($this->getFields());
    }

    public function getFields(): ?array
    {
        if (isset($this->ID) && app()->hasAcf()) {
            return \get_fields($this->ID) ?: null;
        }
        return null;
    }

    public function isMultilanguage(): bool
    {
        return app()->get('multilanguage')->is($this);
    }

    public function slug(string $language_code = null): string
    {
        if ($this->isMultilanguage()) {
            return $this->get('slug', $language_code) ?: $this->post_name;
        }
        return $this->post_name;
    }

    public function postTypeName(
        bool $singular = false,
        string $language = null
    ): string {
        return Query::postTypeName($this->post_type, $singular, $language);
    }

    public function link(string $language_code = null): string
    {
        // if disabled honor external links, but do not fallback
        if ($this->isDisabled($language_code)) {
            return $this->get('external_link', $language_code) ?: '';
        }
        return Link::forPosts($this, $language_code);
    }

    public function redirectLink(string $language_code = null): string
    {
        // if disabled try external links, otherwise fallback to best bet
        if ($this->isDisabled($language_code)) {
            return $this->get('external_link', $language_code) ?: Link::fallback($this, $language_code);
        }
        return '';
    }

    public function isDisabled(string $language_code = null): bool
    {
        return !empty($this->get('is_disabled', $language_code));
    }

    public function terms(string $taxonomy = null, array $args = []): Terms
    {
        return Query::postTerms($this->ID, $taxonomy, $args);
    }

    public function isEmpty(): bool
    {
        return empty($this->ID)
            || empty($this->post_type)
            || parent::isEmpty();
    }
}
