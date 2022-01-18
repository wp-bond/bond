<?php

namespace Bond\Services;

use Bond\Fields\Acf\FieldGroup;
use Bond\Post;
use Bond\Settings\Admin;
use Bond\Settings\Language;
use Bond\Term;
use Bond\Utils\Cast;
use Bond\Utils\Query;
use Bond\Utils\Str;
use WP_Post;
use WP_Term;

class Multilanguage implements ServiceInterface
{
    protected bool $enabled = false;
    protected array $post_types = [];
    protected array $taxonomies = [];

    public function config(
        ?bool $enabled = null,
        ?array $post_types = null,
        ?array $taxonomies = null,
    ) {

        if (isset($enabled)) {
            if ($enabled) {
                $this->enable();
            } else {
                $this->disable();
            }
        }
        if ($post_types) {
            $this->postTypes($post_types);
        }
        if ($taxonomies) {
            $this->taxonomies($taxonomies);
        }
    }

    public function enable()
    {
        if (!$this->enabled) {
            $this->enabled = true;

            // query
            \add_filter('pre_get_posts', [$this, 'queryFilter']);

            // posts

            // TODO VERY IMPORTANT this is not being triggered on WP AJAX
            // WILL RUN twice.. maybe just add a global to protect
            \add_action('acf/save_post', [$this, 'translateAcfFields']);

            // for posts we translate in sync with Bond save post hook
            \add_action('Bond/translate_post', [$this, 'translatePostHook'], 1, 2);

            // for ACF options
            \add_action('Bond/translate_options', [$this, 'translateOptionsHook']);

            // post titles
            \add_filter('the_title', [$this, 'filterPostTitle'], 10, 2);

            // terms

            // we translate in sync with Bond save term hook
            \add_action('Bond/translate_term', [$this, 'translateTermHook'], 1, 2);

            // term names
            \add_filter('term_name', [$this, 'filterTermName'], 10, 2);
            // TODO NOT sticking to typeRadio
            // MUST FIRST look for another wordpress hooks besides the term_name
            \add_filter('acf/fields/taxonomy/result', [$this, 'filterTermName'], 10, 2);


            // Temp hack till I undestand the changes on WP 5.5
            // TODO testing
            // \add_filter('pre_handle_404', function () {
            //     global $wp_query, $bond_unset_404;

            //     if ($bond_unset_404) {
            //         $wp_query->is_404 = false;
            //         $wp_query->is_page = true;
            //         $wp_query->is_singular = true;
            //         return true;
            //     }
            //     // dd($wp_query);
            // });
        }
    }

    public function disable()
    {
        if ($this->enabled) {
            $this->enabled = false;

            // query
            \remove_filter('pre_get_posts', [$this, 'queryFilter']);

            // posts
            \remove_action('acf/save_post', [$this, 'translateAcfFields']);
            \remove_action('Bond/translate_post', [$this, 'translatePostHook'], 1, 2);
            \remove_action('Bond/translate_options', [$this, 'translateOptionsHook']);
            \remove_filter('the_title', [$this, 'filterPostTitle'], 10, 2);

            // terms
            \remove_action('Bond/translate_term', [$this, 'translateTermHook'], 1, 2);
            \remove_filter('term_name', [$this, 'filterTermName'], 10, 2);
            \remove_filter('acf/fields/taxonomy/result', [$this, 'filterTermName'], 10, 2);
        }
    }

    public function postTypes(array $post_types = null): array
    {
        if (empty($post_types)) {
            return $this->post_types;
        }

        $this->post_types = array_merge($this->post_types, $post_types);

        // TODO allow more configuration....
        // some attachments we need titles, others don't

        // Fields
        $this->addFieldsForPosts($post_types);

        // As we are handling our own multilanguage titles
        // let's hide the WP defaults
        Admin::hideTitle($post_types);

        return $this->post_types;
    }

    public function taxonomies(array $taxonomies = null): array
    {
        if (empty($taxonomies)) {
            return $this->taxonomies;
        }

        $this->taxonomies = array_merge($this->taxonomies, $taxonomies);

        // Fields
        $this->addFieldsForTaxonomies($taxonomies);

        return $this->taxonomies;
    }

    public function is($obj): bool
    {
        if ($obj instanceof Post || $obj instanceof WP_Post) {
            return in_array($obj->post_type, $this->post_types);
        }

        if ($obj instanceof Term || $obj instanceof WP_Term) {
            return in_array($obj->taxonomy, $this->taxonomies);
        }

        return false;
    }

    public function queryFilter($query)
    {
        // only the main query
        if (\is_admin() || !$query->is_main_query()) {
            return;
        }
        // dd($query, $query->is_singular, $query->is_page);
        // nothing needs to be handled for the default language
        if (Language::isDefault()) {
            return;
        }

        // sanitize is_page
        if ($query->get('page')) {
            $query->is_page = true;
            $query->is_singular = true;
            // $query->is_home = false;
        }

        // we only translate singles and pages
        if (!$query->is_singular) {
            return;
        }

        // singles
        if ($query->is_single) {
            $post_type = $query->get('post_type');

            // nothing to do
            if (!$post_type) {
                return;
            }

            // get post by meta to find its actual slug
            $found = Query::wpPostBySlug(
                slug: $query->get('name'),
                post_type: $post_type,
                params: [
                    'post_status' => is_user_logged_in() ? 'any' : 'publish',
                ]
            );

            if ($found) {
                // set the actual slug
                $query->set($post_type, $found->post_name);
            }
            // that's all, WP will take from here
            return;
        }

        // pages
        if ($query->is_page) {
            $path = $query->get('page');
            // dd($path);

            // nothing to do
            if (!$path) {
                return;
            }

            // get post by meta to find its actual slug
            $found = Query::wpPostBySlug(
                slug: $path,
                post_type: 'page',
                params: [
                    'post_status' => is_user_logged_in() ? 'any' : 'publish',
                ]
            );

            if ($found) {
                global $bond_unset_404;
                // $bond_unset_404 = true;

                // reparse query
                $query->parse_query([
                    'page_id' => $found->ID,
                ]);
                // unset($query->query['page']);
                // unset($query->query_vars['page']);
                // $query->query['pagename'] = $found->post_name;
                // $query->set('pagename', $found->post_name);
                // $query->set('page_id', '');

                // dd($found, $query);
            } else {
                // $query->is_404 = true;
            }
        }
    }


    // Posts

    public function filterPostTitle($title, $id)
    {
        $post = Cast::post($id);

        // handles only the post_types added here
        if ($post && in_array($post->post_type, $this->post_types)) {
            return $post->title();
        }

        return $title;
    }

    public function translateAcfFields($id)
    {
        $post = Cast::wpPost($id);
        if ($post) {
            $this->translatePostHook($post->post_type, $post->ID);
        }
    }

    public function translateOptionsHook()
    {
        $this->translateAllFields('options');
    }

    public function translatePostHook(string $post_type, int $post_id)
    {
        // Translate all fields
        $this->translateAllFields($post_id);

        // only allowed
        // TODO MAYBE FORGET THIS AT ALL, JUST TRY TO TRANLATE
        if (!in_array($post_type, $this->post_types)) {
            return;
        }

        // Mulilanguage titles and slugs
        $this->ensurePostTitleAndSlug($post_id);
    }


    public function translateAllFields($post_id)
    {
        if (
            !app()->translation()->hasTranslationApi()
            || !app()->hasAcf()
        ) {
            return;
        }

        // get all post fields
        $acf = \get_field_objects($post_id, false);
        if (empty($acf)) {
            return;
        }

        // assemble a control field list
        $fields = [];
        foreach ($acf as $name => $values) {
            $fields[$name] = [
                'type' => $values['type'],
                'value' => $this->acfFieldValue($values),
            ];
        }

        // dd(get_field_objects($post_id, false), $fields);

        // translate
        $updated = 0;
        $translated = $this->translateMissingFields(
            $fields,
            true,
            $updated
        );
        // dd($translated, $updated);

        // save
        foreach ($translated as $name => $value) {
            \update_field($name, $value, $post_id);
        }
    }


    private function acfFieldValue($values)
    {
        if (in_array($values['type'], [
            'flexible_content',
            'repeater',
        ])) {

            // put together all sub fields
            if ($values['type'] === 'repeater') {
                $values['layouts'] = [
                    ['sub_fields' => $values['sub_fields']]
                ];
            }
            $lookup = [];
            foreach ($values['layouts'] as $layout) {
                foreach ($layout['sub_fields'] as $sub) {
                    $lookup[$sub['key']] = [
                        'name' => $sub['name'],
                        'type' => $sub['type'],
                        'layouts' => $sub['layouts'] ?? null,
                        'sub_fields' => $sub['sub_fields'] ?? null,
                    ];
                }
            }

            // format all sub values
            $value = [];
            if (!empty($values['value'])) {
                foreach ($values['value'] as $layout) {
                    $layout_value = [];

                    foreach ($layout as $k => $v) {

                        if ($k === 'acf_fc_layout') {
                            $layout_value[$k] = [
                                'type' => '',
                                'value' => $v,
                            ];
                        } else {
                            $field = $lookup[$k];
                            $field['value'] = $v;

                            $layout_value[$field['name']] = [
                                'type' => $field['type'],
                                'value' => $this->acfFieldValue($field),
                            ];
                        }
                    }

                    $value[] = $layout_value;
                }
            }
            return $value;
        }

        if ($values['type'] === 'group') {

            // put together all sub fields
            $lookup = [];
            foreach ($values['sub_fields'] as $sub) {
                $lookup[$sub['key']] = [
                    'name' => $sub['name'],
                    'type' => $sub['type'],
                    'layouts' => $sub['layouts'] ?? null,
                    'sub_fields' => $sub['sub_fields'] ?? null,
                ];
            }

            // format all sub values
            $value = [];
            if (!empty($values['value'])) {
                foreach ($values['value'] as  $k => $v) {

                    $field = $lookup[$k];
                    $field['value'] = $v;

                    $value[$field['name']] = [
                        'type' => $field['type'],
                        'value' => $this->acfFieldValue($field),
                    ];
                }
            }

            return $value;
        }

        // for all other field types, just return the value
        return $values['value'];
    }


    protected function translateMissingFields(
        array $target,
        $narrow = true,
        &$changed = 0
    ): array {

        $translated = [];

        foreach ($target as $key => $values) {

            $type = $values['type'];
            $value = $values['value'];

            // recurse if is flex / group / repeater
            if (in_array($type, [
                'flexible_content',
                'repeater',
                'group',
            ])) {
                $before = $changed;
                $result = [];

                if ($type === 'group') {
                    $result = $this->translateMissingFields($value, false, $changed);
                } else {
                    foreach ($value as $v) {
                        $result[] = $this->translateMissingFields($v, false, $changed);
                    }
                }

                // include entire array if not narrow
                // or if something was translated
                if (!$narrow || $before !== $changed) {
                    $translated[$key] = $result;
                }
                continue;
            }

            // only translate these field types
            // also only translate empty strings
            if (!in_array($type, [
                'text',
                'textarea',
                'wysiwyg',
            ]) || $value !== '') {

                // store values if recursing, otherwise just skip
                if (!$narrow) {
                    $translated[$key] = $value;
                }
                continue;
            }

            // for empty string that is suffixed with a lang
            // we will try to find a match in another language
            // and translate

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
                        $fv = $target[$lang_key] ?? [];
                        $v = $fv['value'] ?? null;

                        if (empty($v) || !is_string($v)) {
                            continue;
                        }
                        if (Str::isEmail($v) || Str::isUrl($v)) {
                            continue;
                        }

                        // translated
                        $t = app()->translation()->fromTo($c, $code, $v);
                        if ($t) {

                            // TEMP fix to remove <br /> from textareas
                            // if (
                            //     strpos($v, '<br /> ') === false
                            //     && strpos($t, '<br /> ') !== false
                            // ) {
                            //     $t = str_replace('<br /> ', "\n", $t);
                            // }

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
                    $default_title = app()->translation()->fromTo($code, $default_code, $title);
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
                    app()->translation()->fromTo($default_code, $code, $default_title) ?: $default_title,
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

            $slug = (string)\get_field('slug' . $suffix, $post->ID);

            // remove parent pages
            if (strpos($slug, '/') !== false) {
                $slug = substr($slug, strrpos($slug, '/') + 1);
            }

            // get from title if empty
            if (empty($slug)) {
                $slug = \get_field('title' . $suffix, $post->ID);
            }

            // sanitize user input
            $slug = Str::kebab($slug);


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

        // TEMP remove attachments
        if ($i = array_search('attachment', $location)) {
            array_splice($location, $i, 1);
        }

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
            ->order(99) // ACF issue, if we use screen hide options they must come first, so we need this high menuOrder to avoid that
            ->positionAfterTitle()
            ->location($location);

        foreach (Language::codes() as $code) {
            $suffix = Language::fieldsSuffix($code);
            $label = Language::fieldsLabel($code);

            $group->textField('title' . $suffix)
                ->label('Title ' . $label, 'en')
                ->wrapWidth(60);

            $group->textField('slug' . $suffix)
                ->label('Slug ' . $label, 'en')
                ->wrapWidth(30);

            $group->messageField('bond_link_message_icon' . $suffix)
                ->label('Link ' . $label, 'en')
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

                        echo '<a href="' . $link . '" target="_blank" rel="noopener noreferrer" class="bond-link-arrow">↗</a>';
                    }
                );
            }
        }
    }



    // Taxonomy

    public function filterTermName($name, $term)
    {
        $term = Cast::term($term);

        // handles only the taxonomy added here
        if ($term && in_array($term->taxonomy, $this->taxonomies)) {
            return $term->name();
        }

        return $name;
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

            // we must use WP fields for the default language
            if (Language::isDefault($code)) {
                continue;
            }

            $suffix = Language::fieldsSuffix($code);
            $label = Language::fieldsLabel($code);

            $group->textField('name' . $suffix)
                ->label('Name ' . $label, 'en');

            $group->textField('slug' . $suffix)
                ->label('Slug ' . $label, 'en');
        }
    }

    public function translateTermHook(string $taxonomy, int $term_id)
    {
        // only allowed
        if (!in_array($taxonomy, $this->taxonomies)) {
            return;
        }

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
                $name = app()->translation()->fromTo($default_code, $code, $term->name);
                \update_field('name' . $suffix, $name, $field_id);
            }

            // ensure slugs
            if (empty($slug)) {
                if (!empty($name)) {
                    $slug = Str::kebab($name);
                    \update_field('slug' . $suffix, $slug, $field_id);
                }
            } else {
                // ensure it's slugfied
                if ($slug !== Str::kebab($slug)) {
                    $slug = Str::kebab($slug);
                    \update_field('slug' . $suffix, $slug, $field_id);
                }
            }
        }
    }
}
