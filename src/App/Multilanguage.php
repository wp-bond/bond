<?php

namespace Bond\App;

use Bond\Fields\Acf\FieldGroup;
use Bond\Settings\Admin;
use Bond\Settings\Language;
use Bond\Utils\Cast;
use Bond\Utils\Query;
use Bond\Utils\Str;

class Multilanguage
{
    // TODO we need to move here the pre_get_posts handling

    protected array $post_types = [];
    protected array $taxonomies = [];
    protected bool $added_post_hook = false;
    protected bool $added_term_hook = false;


    public function postTypes(array $post_types)
    {
        if (empty($post_types)) {
            return;
        }

        $this->post_types = array_merge($this->post_types, $post_types);

        // Translate
        $this->addTranslatePostHook();

        // Filter the post title
        $this->filterPostTitle();

        // Fields
        $this->addFieldsForPosts($post_types);

        // As we are handling our own multilanguage titles
        // let's hide the WP defaults
        Admin::hideTitle($post_types);
    }

    public function taxonomies(array $taxonomies)
    {
        if (empty($taxonomies)) {
            return;
        }

        $this->taxonomies = array_merge($this->taxonomies, $taxonomies);

        // Translate
        $this->addTranslateTermHook();

        // filter the post title
        $this->filterTaxonomyName();

        // Fields
        $this->addFieldsForTaxonomies($taxonomies);
    }


    // Posts

    protected function filterPostTitle()
    {
        static $enabled = null;
        if ($enabled) {
            return;
        }
        $enabled = true;

        \add_filter('the_title', function ($title, $id) {
            $post = Cast::post($id);

            // handles only the post_types added here
            if ($post && in_array($post->post_type, $this->post_types)) {
                return $post->title ?: $post->post_title;
            }

            return $title;
        }, 10, 2);
    }

    public function addTranslatePostHook()
    {
        if ($this->added_post_hook) {
            return;
        }
        $this->added_post_hook = true;

        // for posts we translate in sync with Bond save post hook
        \add_action('Bond/translate_post', [$this, 'translatePostHook'], 1);

        // for ACF options
        \add_action('Bond/translate_options', [$this, 'translateOptionsHook']);
    }

    public function removeTranslatePostHook()
    {
        $this->added_post_hook = false;

        \remove_action('Bond/translate_post', [$this, 'translatePostHook'], 1);

        \remove_action('Bond/translate_options', [$this, 'translateOptionsHook']);
    }


    public function translateOptionsHook()
    {
        $this->translateAllFields('options');
    }

    public function translatePostHook(int $post_id)
    {
        // Translate all fields
        // auto activated, later can allow config
        $this->translateAllFields($post_id);

        // Mulilanguage titles and slugs
        $this->ensurePostTitleAndSlug($post_id);
    }


    public function translateAllFields($post_id)
    {
        if (
            !app()->get('translation')->hasService()
            || !app()->hasAcf()
        ) {
            return;
        }

        // TODO it's not working well for file fields with url inside Flex fields
        // could use get_field_objects($post_id, false) to reconstruct the keys, with the values not formated
        $fields = \get_fields($post_id);
        if (empty($fields)) {
            return;
        }

        $updated = 0;
        $translated = $this->translateMissingFields(
            (array) $fields,
            true,
            $updated
        );
        // dd($translated, $updated);

        foreach ($translated as $name => $value) {
            \update_field($name, $value, $post_id);
        }
    }

    protected function translateMissingFields(
        array $target,
        $narrow = true,
        &$changed = 0
    ): array {

        $translated = [];
        // dd($target);

        foreach ($target as $key => $value) {

            // recurse
            if (is_array($value)) {

                $before = $changed;
                $result = $this->translateMissingFields($value, false, $changed);

                // include entire array if not narrow
                // or if something was translated
                if (!$narrow || $before !== $changed) {
                    $translated[$key] = $result;
                }
                continue;
            }

            // only empty strings will pass
            if ($value !== '') {
                if ($narrow) {
                    continue;
                } else {
                    $translated[$key] = $value;
                    continue;
                }
            }

            // for empty string that is suffixed with a lang
            // we will try to find a match in another language
            // and translate

            $translation = app()->get('translation');

            foreach (Language::codes() as $code) {

                $suffix = Language::fieldsSuffix($code);

                if (str_ends_with($key, $suffix)) {

                    $unlocalized_key = substr($key, 0, -strlen($suffix));

                    foreach (Language::codes() as $c) {
                        if ($c === $code) {
                            continue;
                        }

                        // key
                        $lang_key = $unlocalized_key . Language::fieldsSuffix($c);

                        // value
                        $v = $target[$lang_key] ?? '';
                        if (empty($v) || !is_string($v)) {
                            continue;
                        }
                        if (Str::isEmail($v) || Str::isUrl($v)) {
                            continue;
                        }

                        // translated
                        $t = $translation->fromTo($c, $code, $v);
                        if ($t) {
                            $translated[$key] = $t;
                            $changed++;
                            break 2;
                        }
                    }
                }
            }
        }

        return $translated;
    }


    protected function ensurePostTitleAndSlug(int $post_id)
    {
        if (!app()->hasAcf()) {
            return;
        }

        $post = Cast::wpPost($post_id);
        if (!$post) {
            return;
        }
        // not for front page
        if ($post->post_type === 'page' && \is_front_page()) {
            return;
        }

        $codes = Language::codes();
        $translation = app()->get('translation');

        // get default language's title
        $default_code = Language::defaultCode();
        $default_suffix = Language::defaultFieldsSuffix();
        $default_title = \get_field('title' . $default_suffix, $post->ID);

        // or get the next best match
        if (empty($default_title)) {
            foreach ($codes as $code) {
                if ($code === $default_code) {
                    continue;
                }

                $suffix = Language::fieldsSuffix($code);
                $title = \get_field('title' . $suffix, $post->ID);

                // if found, we will translate and set the default_title
                if (!empty($title)) {
                    $default_title = $translation->fromTo($code, $default_code, $title);
                    break;
                }
            }

            // if still empty, try WP title
            // but only if it is not auto-draft
            if (
                empty($default_title)
                && $post->post_status !== 'auto-draft'
            ) {
                $default_title = $post->post_title;
            }

            // store default title
            if (!empty($default_title)) {
                \update_field(
                    'title' . $default_suffix,
                    $default_title,
                    $post->ID
                );
            }
        }

        // no title yet, just skip
        if (empty($default_title)) {
            return;
        }

        // if WP title is different, update it
        if (
            $post->post_title !== $default_title
            && in_array($post->post_type, $this->post_types)
        ) {
            // TODO we are updating the front page too, should not
            // maybe get from options page_on_front, it matches, skip
            \wp_update_post([
                'ID' => $post->ID,
                'post_title' => $default_title,
            ]);
            $post->post_title = $default_title;
        }

        // we don't allow empty titles
        foreach ($codes as $code) {
            if ($code === $default_code) {
                continue;
            }

            $suffix = Language::fieldsSuffix($code);
            $title = \get_field('title' . $suffix, $post->ID);

            if (empty($title)) {
                \update_field(
                    'title' . $suffix,
                    $translation->fromTo($default_code, $code, $default_title) ?: $default_title,
                    $post->ID
                );
            }
        }


        // title is done
        // now it's time for the slug

        // not published yet, don't need to handle
        if (empty($post->post_name)) {
            return;
        }

        $is_hierarchical = \is_post_type_hierarchical($post->post_type);

        foreach ($codes as $code) {
            $suffix = Language::fieldsSuffix($code);

            $slug = \get_field('slug' . $suffix, $post->ID);

            // remove parent pages
            if (strpos($slug, '/') !== false) {
                $slug = substr($slug, strrpos($slug, '/') + 1);
            }

            // get from title if empty
            if (empty($slug)) {
                $slug = \get_field('title' . $suffix, $post->ID);
            }

            // sanitize user input
            $slug = Str::slug($slug);


            // handle
            if (Language::isDefault($code)) {

                // define full path if is hierarchical
                $parent_path = [];
                if ($is_hierarchical) {
                    $p = $post;

                    while ($p->post_parent) {
                        $p = \get_post($p->post_parent);
                        $parent_path[] = $p->post_name;
                    }
                    $parent_path = array_reverse($parent_path);

                    $post_path = implode('/', array_merge($parent_path, [$slug]));
                } else {
                    $post_path = $slug;
                }

                // always update both WP and ACF
                // WP always needs, as the user can change anytime
                // ACF needs the first time, or if programatically changed
                $id = \wp_update_post([
                    'ID' => $post->ID,
                    'post_name' => $slug,
                ]);
                if ($id) {

                    // sync with the actual WP slug
                    // the slug above might have changed in some scenarios, like multiple posts with same slug
                    // so we fetch again
                    $slug = Query::slug($id);

                    // join if hierarchical
                    if ($is_hierarchical) {
                        $post_path = implode('/', array_merge($parent_path, [$slug]));
                    } else {
                        $post_path = $slug;
                    }
                }
                \update_field('slug' . $suffix, $post_path, $post->ID);
            } else {


                // prepend parent path
                // only one level down, because the parent already has the translated slug
                if ($is_hierarchical) {
                    $parent_path = [];

                    if ($post->post_parent) {
                        $p = Cast::post($post->post_parent);
                        if ($p) {
                            $parent_path[] = $p->slug($code);
                        }
                    }

                    $post_path_intent = implode('/', array_merge($parent_path, [$slug]));
                } else {
                    $post_path_intent = $slug;
                }

                $post_path = $post_path_intent;

                // search for posts with same slug, and increment until necessary
                $i = 1;
                while (Query::wpPostBySlug(
                    $post_path,
                    $post->post_type,
                    $code,
                    [
                        'post__not_in' => [$post->ID],
                    ]
                )) {
                    $post_path = $post_path_intent . '-' . (++$i);
                }

                // done, update ACF field
                \update_field(
                    'slug' . $suffix,
                    $post_path,
                    $post->ID
                );
            }
        }
    }

    protected function addFieldsForPosts(array $post_types)
    {
        $location = $post_types;

        // don't translate the Home page
        if ($i = array_search('page', $location)) {

            array_splice($location, $i, 1);

            $location[] = [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'page',
                ],
                [
                    'param' => 'page_type',
                    'operator' => '!=',
                    'value' => 'front_page',
                ],
            ];
        }

        // Fields
        $group = (new FieldGroup('bond_multilanguage_post'))
            ->title('Title / Link', 'en')
            // ->screenHideAll()
            ->menuOrder(99) // ACF issue, if we use screen hide options they must come first, so we need this high menuOrder to avoid that
            ->positionAfterTitle()
            ->location($location);

        foreach (Language::codes() as $code) {
            $suffix = Language::fieldsSuffix($code);
            $label = Language::fieldsLabel($code);

            $group->textField('title' . $suffix)
                ->label('Title' . $label, 'en')
                ->wrapWidth(60);

            $group->textField('slug' . $suffix)
                ->label('Slug' . $label, 'en')
                ->wrapWidth(30);

            $group->messageField('bond_link_message_icon' . $suffix)
                ->label('Link' . $label, 'en')
                ->wrapWidth(10);
        }


        // link message
        static $already = null;
        if (!$already) {
            $already = true;

            foreach (Language::codes() as $code) {
                $suffix = Language::fieldsSuffix($code);

                \add_filter(
                    'acf/render_field/name=bond_link_message_icon' . $suffix,
                    function () use ($code) {
                        global $post;

                        $post = Cast::post($post);
                        if (!$post) {
                            return;
                        }

                        $link = $post->link($code);
                        if (!$link) {
                            return;
                        }

                        echo '<a href="' . $link . '" target="_blank" rel="noopener" class="bond-link-arrow">↗</a>';
                    }
                );
            }
        }
    }



    // Taxonomy

    protected function filterTaxonomyName()
    {
        static $enabled = null;
        if ($enabled) {
            return;
        }
        $enabled = true;


        \add_filter('term_name', function ($pad_tag_name, $term) {

            $term = Cast::term($term);

            // handles only the taxonomy added here
            if ($term && in_array($term->taxonomy, $this->taxonomies)) {
                return $term->get('name', Language::code()) ?: $term->name;
            }

            return $term->name ?? '';
        }, 10, 2);


        // TODO NOT Sticking to typeRadio
        // MUST FIRST look for another wordpress hooks besides the term_name

        \add_filter('acf/fields/taxonomy/result', function ($title, $term) {

            $term = Cast::term($term);

            // handles only the taxonomy added here
            if ($term && in_array($term->taxonomy, $this->taxonomies)) {
                return $term->get('name', Language::code());
            }
            //    TODO teste the limite stirng, and remove
            return $term->name ?? '';
        }, 10, 2);
    }


    protected function addFieldsForTaxonomies(array $taxonomies)
    {
        // add default language label to wp fields
        foreach ($taxonomies as $taxonomy) {
            $lang =  ' ' . Language::defaultFieldsLabel();

            Admin::setTaxonomyFields($taxonomy, [
                'name_label_after' => $lang,
                'slug_label_after' => $lang,
            ]);
        }


        // add fields
        $group = (new FieldGroup('bond_multilanguage_tax'))
            ->location([
                'tax' => $taxonomies,
            ]);

        foreach (Language::codes() as $code) {

            // we must to use WP fields for the default language
            if (Language::isDefault($code)) {
                continue;
            }

            $suffix = Language::fieldsSuffix($code);
            $label = Language::fieldsLabel($code);

            $group->textField('name' . $suffix)
                ->label('Name' . $label, 'en');

            $group->textField('slug' . $suffix)
                ->label('Slug' . $label, 'en');
        }
    }

    public function addTranslateTermHook()
    {
        if ($this->added_term_hook) {
            return;
        }
        $this->added_term_hook = true;

        // we translate in sync with Bond save term hook
        \add_action('Bond/translate_term', [$this, 'translateTermHook'], 1, 2);
    }

    public function removeTranslateTermHook()
    {
        $this->added_term_hook = false;

        \remove_action('Bond/translate_term', [$this, 'translateTermHook'], 1, 2);
    }

    public function translateTermHook(string $taxonomy, int $term_id)
    {
        // Translate all fields
        // auto activated, later can allow config
        $this->translateAllFields($taxonomy . '_' . $term_id);

        // Mulilanguage titles and slugs
        $this->translateTermNameAndSlug($taxonomy, $term_id);
    }

    protected function translateTermNameAndSlug(string $taxonomy, int $term_id)
    {
        $term = Query::wpTerm($term_id);
        if (!$term) {
            return;
        }
        $field_id = $taxonomy . '_' . $term_id;
        $translation = app()->get('translation');
        $default_code = Language::defaultCode();

        foreach (Language::codes() as $code) {
            if (Language::isDefault($code)) {
                continue;
            }
            $suffix = Language::fieldsSuffix($code);
            $name = \get_field('name' . $suffix, $field_id);
            $slug = \get_field('slug' . $suffix, $field_id);

            // ensure names
            if (empty($name)) {
                $name = $translation->fromTo($default_code, $code, $term->name);
                \update_field('name' . $suffix, $name, $field_id);
            }

            // ensure slugs
            if (empty($slug)) {
                if (!empty($name)) {
                    $slug = Str::slug($name);
                    \update_field('slug' . $suffix, $slug, $field_id);
                }
            } else {
                // ensure it's slugfied
                if ($slug !== Str::slug($slug)) {
                    $slug = Str::slug($slug);
                    \update_field('slug' . $suffix, $slug, $field_id);
                }
            }
        }
    }
}
