<?php

namespace Bond;

use Bond\Utils\Link;
use Bond\Support\Fluent;
use Bond\Utils\Cast;
use Bond\Utils\Date;
use Bond\Utils\Image;
use Bond\Utils\Query;
use Bond\Utils\Str;
use Carbon\Carbon;
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


    // IDEA
    // don't store fields values into the object itself
    // keep into a 'fields' var
    // this way we could ensure the case for a multilanguage value stored without suffix


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
            // auto load if possible
            if (isset($this->post_name)) {
                $values = $this->post_name;
            } else {
                return;
            }
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

    public function isEmpty(): bool
    {
        return empty($this->ID)
            || empty($this->post_type)
            || parent::isEmpty();
    }

    public function isMultilanguage(): bool
    {
        return app()->multilanguage()->is($this);
    }

    public function postTypeName(
        bool $singular = false,
        string $language = null
    ): string {
        return Query::postTypeName($this->post_type, $singular, $language);
    }


    // TODO maybe use __call for all methods like this?
    // for example I have subtitle in my project, and it could be better fit to use a method instead?
    public function title(string $language = null): string
    {
        if ($this->isMultilanguage()) {
            return $this->get('title', $language) ?: $this->post_title;
        }
        return $this->post_title;
    }

    public function slug(string $language = null): string
    {
        if (!isset($this->post_name)) {
            return '';
        }
        if ($this->isMultilanguage()) {
            return $this->get('slug', $language) ?: $this->post_name;
        }
        return $this->post_name;
    }

    public function content(string $language = null): string
    {
        if ($this->isMultilanguage()) {
            return $this->get('content', $language) ?: '';
        }
        return Str::filterContent($this->content ?: $this->post_content);
    }

    // TODO consider adding url() as a shortcut to app()->url()+link
    // if so, add to PostType and Tax as well

    // TODO review again the external_link, as it is a url, not a link, therefore should not even be returned at the link method. And redirectLink would need to be renamed to redirectUrl

    public function link(string $language = null): string
    {
        // if disabled honor external links, but do not fallback
        if ($this->isDisabled($language)) {
            return $this->get('external_link', $language) ?: '';
        }
        return Link::forPosts($this, $language);
    }

    public function redirectLink(string $language = null): string
    {
        // if disabled try external links, otherwise fallback to best bet
        if ($this->isDisabled($language)) {
            return $this->get('external_link', $language) ?: Link::fallback($this, $language);
        }
        return '';
    }

    /**
     * Checks if the post is disabled for the current language.
     *
     * This is a common feature for multilanguage projects as it allows to publish a post in one language and disable in others.
     */
    public function isDisabled(string $language = null): bool
    {
        return !empty($this->get('is_disabled', $language));
    }

    public function date(): Carbon
    {
        return Date::carbon($this->post_date);
    }

    public function dateGmt(): Carbon
    {
        return Date::carbon($this->post_date_gmt, 'gmt');
    }

    public function modified(): Carbon
    {
        return Date::carbon($this->post_modified);
    }

    public function modifiedGmt(): Carbon
    {
        return Date::carbon($this->post_modified_gmt, 'gmt');
    }

    /** Get the post author. IMPORTANT this is the WP Editor that created the post itself so be aware that it may not be the actual content author, depending on your case. */
    public function author(): ?User
    {
        return Cast::user((int)$this->post_author);
    }

    public function terms(string $taxonomy = null, array $args = []): Terms
    {
        return Query::postTerms($this->ID, $taxonomy, $args);
    }

    public function termsIds(string $taxonomy = null, array $args = []): array
    {
        return $this->terms($taxonomy, $args)->ids();
    }


    public function thumbnailId(): int
    {
        return (int) \get_post_thumbnail_id($this->ID);
    }

    public function archiveImage(): ?Attachment
    {
        return Cast::post($this->archiveImageId());
    }

    public function archiveImageId(): int
    {
        return (int) $this->archive_image;
    }

    public function image(): ?Attachment
    {
        return Cast::post($this->imageId());
    }

    public function imageId(): int
    {
        // return id if is attachment already
        if ($this->post_type === 'attachment') {
            return (int) $this->ID;
        }

        // try common ACF image fields
        // IMPORTANT relies that the return_type is id
        if ($this->image) {
            return (int) $this->image;
        }
        if ($this->archive_image) {
            return (int) $this->archive_image;
        }
        if ($this->feature_image) {
            return (int) $this->feature_image;
        }
        // gallery field
        if (!empty($this->images[0])) {
            return (int) $this->images[0];
        }

        // modules
        $images = $this->modulesImages();
        if (count($images)) {
            return (int) $images[0];
        }

        // thumbnail
        if ($id = $this->thumbnailId()) {
            return $id;
        }

        // raw body content
        $content = $this->content();
        $images = Image::findWpImages($content);
        if (count($images)) {
            return (int) $images[0];
        }

        return 0;
    }

    // looks for a very generic ACF flex field called 'modules'
    protected function modulesImages(): array
    {
        if (empty($this->modules)) {
            return [];
        }
        $images = [];

        foreach ($this->modules as $module) {

            if (is_int($module['image'])) {
                $images[] = $module['image'];
            }

            if (is_iterable($module['images'])) {
                foreach ($module['images'] as $image) {

                    if (is_int($image)) {
                        $images[] = $image;
                    } elseif (
                        !empty($images['image'])
                        && is_int($images['image'])
                    ) {
                        $images[] = $image;
                    }
                }
            }
        }
        return array_map('intval', $images);
    }
}
