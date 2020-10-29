<?php

namespace Bond\App;

use Bond\Support\Fluent;
use Bond\Utils\Query;
use Bond\Utils\Str;
use InvalidArgumentException;


// TODO GREAT IDEA! MAYBE to not have to use extract, the view sent would actually be the Fluent itself
// We would use $this->myvalue on the views, and stil be able to use $this->partial()
// View would just need to extend Fluent
// Maybe its good as the template would not feel too loose
//////////// BUT still the templating looks great as direct access to vars, we just still do too much validation isset emtpy




// TODO maybe extend the whole view from Fluent, and do not turn this into static.
// Would it be fine for View to be a Fluent object with the template loaders?


// TODO test WP_USE_THEMES = false to see what happens
// maybe we just do that as it is cleaner
// could still leave the template_redirect action below anyway



/**
 * Template loader utility that builds upon the WP template hierarchy, allowing a logical and compartmentalised folder/file organization.
 *
 * Besides loading template files, it offers the same approach to provide data to the templates, through the WP action & filter hooks, allowing a separation of data logic and the template files.
 *
 * This class does not override the default WordPress template loader, so you can safely mix and match both approaches any time.
 *
 */

class View
{
    /**
     * @var array
     */
    protected static $directories = [];

    /**
     * @var array
     */
    protected $lookup_order = [];

    /**
     * @var string
     */
    protected $templates_dir = 'templates';

    /**
     * @var string
     */
    protected $partials_dir = 'partials';

    /**
     * @var array
     */
    protected Fluent $view_data;

    /**
     * Create instance, setting the prefix which should be used when WP filter and action hooks are triggered.
     */
    public function __construct()
    {
        $this->view_data = new Fluent();

        // do not initialize when it is on WP admin
        // nor when programatically loading WP
        if (!is_admin() && (!defined('WP_USE_THEMES') || WP_USE_THEMES)) {
            $this->init();
        }
    }

    protected function init()
    {
        // define lookup order
        $this->autoSetOrder();

        // handle hooks
        if (did_action('init')) {
            $this->triggerInitActions();
        } else {
            add_action('init', [$this, 'triggerInitActions'], 20);
        }

        if (did_action('wp')) {
            $this->triggerReadyActions();
        } else {
            add_action('wp', [$this, 'triggerReadyActions'], 20);
        }

        if (did_action('template_redirect')) {
            $this->triggerRedirectActions();
        } else {
            add_action('template_redirect', [$this, 'triggerRedirectActions'], 20);
        }
    }

    /**
     * Triger the 'ready' actions according the current lookup order, in reverse,
     * so the most specific superseeds the least.
     *
     * For example, for a single post page, it will trigger, in the following order:
     * [hook_prefix]/ready
     * [hook_prefix]/ready/singular
     * [hook_prefix]/ready/single
     * [hook_prefix]/ready/[post-type]
     * [hook_prefix]/ready/single-[post-type]
     * [hook_prefix]/ready/single-[post-type]-[post-slug]
     *
     * Note: the default hook prefix is 'Bond'.
     */
    public function triggerInitActions()
    {
        if (did_action('Bond/init')) {
            return;
        }

        do_action('Bond/init');
        do_action('Bond/init' . $this->deviceSuffix());

        $order = $this->lookup_order;

        foreach (array_reverse($this->lookup_order) as $name) {

            // if the order changed at runtime, cancel it
            if ($order !== $this->lookup_order) {
                break;
            }

            do_action('Bond/init/' . $name);
            do_action('Bond/init/' . $name . $this->deviceSuffix());
        }
    }

    public function triggerReadyActions()
    {
        if (did_action('Bond/ready')) {
            return;
        }

        do_action('Bond/ready');
        do_action('Bond/ready' . $this->deviceSuffix());

        $order = $this->lookup_order;

        foreach (array_reverse($this->lookup_order) as $name) {

            // if the order changed at runtime, cancel it
            if ($order !== $this->lookup_order) {
                break;
            }

            do_action('Bond/ready/' . $name);
            do_action('Bond/ready/' . $name . $this->deviceSuffix());
        }
    }

    public function triggerRedirectActions()
    {
        if (did_action('Bond/template_redirect')) {
            return;
        }

        do_action('Bond/template_redirect');
        do_action('Bond/template_redirect' . $this->deviceSuffix());

        $order = $this->lookup_order;

        foreach (array_reverse($this->lookup_order) as $name) {

            // if the order changed at runtime, cancel it
            if ($order !== $this->lookup_order) {
                break;
            }

            do_action('Bond/template_redirect/' . $name);
            do_action('Bond/template_redirect/' . $name . $this->deviceSuffix());
        }
    }

    /**
     * Gets order which the templates will be looked up.
     *
     * @return array
     */
    public function getOrder()
    {
        return $this->lookup_order;
    }

    public function prependOrder($lookup_order)
    {
        $this->lookup_order = array_merge(
            (array) $lookup_order,
            $this->lookup_order
        );
    }

    public function addOrder($lookup_order)
    {
        $this->lookup_order = array_merge(
            $this->lookup_order,
            (array) $lookup_order
        );
    }

    /**
     * Sets the order which the templates will be looked up.
     *
     * @param boolean|array|\WP_Query $with
     */
    public function setOrder($with)
    {
        if (!$with) {
            return;
        }

        if (is_array($with)) {

            $this->lookup_order = $with;
        } elseif (is_a($with, 'WP_Query')) {

            $this->lookup_order = $this->defineLookupOrder($with);
        } elseif (!is_admin()) {

            $this->autoSetOrder();
        }
    }

    public function isTemplate($name)
    {
        return in_array($name, $this->lookup_order);
    }

    /**
     * Automatically sets the template lookup order based on the global $wp_query object.
     * If $wp_query is not set yet (before 'wp' action hook) it will hook into the 'wp' action with
     * priority 1, to set the order as soon as possible and not interfere with other actions.
     *
     */
    public function autoSetOrder()
    {
        if (did_action('wp')) {

            // set order with global WP_Query
            global $wp_query;
            $this->lookup_order = $this->defineLookupOrder($wp_query);
        } else {
            // set later, when WP is ready
            add_action('wp', [$this, __FUNCTION__], 1);
        }
    }

    /**
     * Get default templates sub-directory.
     *
     * @return string
     */
    public function getTemplatesDir()
    {
        return $this->templates_dir;
    }

    /**
     * Set default templates sub-directory, relative to theme or parent theme.
     * Paths can or cannot have slashes, don't worry.
     *
     * @param string $dir_name
     */
    public function setTemplatesDir($dir_name)
    {
        $this->templates_dir = trim($dir_name, '/');
    }

    /**
     * Get default partials sub-directory.
     *
     * @return string
     */
    public function getPartialsDir()
    {
        return $this->partials_dir;
    }

    /**
     * Set default partials sub-directory, relative to theme or parent theme.
     * Paths can or cannot have slashes, don't worry.
     *
     * @param string $dir_name
     */
    public function setPartialsDir($dir_name)
    {
        $this->partials_dir = trim($dir_name, '/');
    }

    /**
     * Adds data to the internal storage, which will be used by all subsequent load calls.
     *
     * @param array|object $data
     */
    public function add($data)
    {
        $this->view_data->add($data);
    }

    /**
     * Removes data from the internal storage.
     *
     * @param string $key
     */
    public function remove($key)
    {
        unset($this->view_data[$key]);
    }

    public function increment($key)
    {
        if (!isset($this->view_data[$key])) {
            $this->view_data[$key] = 0;
        }
        return ++$this->view_data[$key];
    }

    public function append($key, $value)
    {
        if (!isset($this->view_data[$key])) {
            $this->view_data[$key] = '';
        }

        $this->view_data[$key] .= (string) $value;

        return $this->view_data[$key];
    }

    // public function appendArray($key, $value)
    // {
    //     if (!isset($this->view_data[$key])) {
    //         $this->view_data[$key] = [];
    //     }

    //     $this->view_data[$key] = array_merge($this->view_data[$key], [$value]);

    //     return $this->view_data[$key];
    // }

    /**
     * Gets the currently stored key.
     *
     * @return array
     */
    public function get($key)
    {
        return $this->view_data[$key];
    }

    /**
     * Gets the currently stored data.
     *
     * @return array
     */
    public function all()
    {
        return $this->view_data->all();
    }

    /**
     * Overwrite the current stored data, which will be used by all subsequent load calls.
     *
     * @param array $data
     */
    public function replaceAll(array $data)
    {
        $this->view_data = new Fluent($data);
    }




    /**
     * Load a matching file inside the current templates dir.
     *
     * @param string $base_name
     * @param array|Fluent $data
     */
    public function template($base_name, $data = [], $include_global_data = true)
    {
        $this->load($this->templates_dir, $base_name, $data, $include_global_data);
    }

    /**
     * Load a matching file inside the current partials dir.
     *
     * @param string $base_name
     * @param array|Fluent $data
     */
    public function partial(
        $base_name,
        $data = [],
        $include_global_data = false
    ) {
        $this->load($this->partials_dir, $base_name, $data, $include_global_data);
    }

    /**
     * Load a template file matching the current lookup order.
     *
     * @param string $dir_name
     * @param string $base_name
     * @param array|Fluent $data
     */
    protected function load($dir_name, $base_name, $data = [], $include_global_data = true)
    {
        // sanitize and scan dir, if not already scanned
        $dir_name = trim($dir_name, '/');
        $this->scanDir($dir_name);

        // find the template file
        list($found, $template_path) = $this->find($base_name, $dir_name);

        // return if not found
        if (!$template_path) {
            return;
        }

        //
        if (is_array($data)) {
            $data = new Fluent($data);
        } elseif (!($data instanceof Fluent)) {
            throw new InvalidArgumentException('Only Array or Fluent');
        }



        // merge input data with the global
        if ($include_global_data) {
            // optimize this?
            foreach ($this->all() as $k => $v) {
                if (!isset($data[$k])) {
                    $data[$k] = $v;
                }
            }
        }

        // localize
        $data->localize();

        // trigger action before render, good to inject html before and after
        \do_action('Bond/load/' . $dir_name . '/' . $found);


        // TODO maybe bind the closure, to not extract here..
        // https://www.php.net/manual/en/class.closure.php

        // extract data as php vars
        extract($data->all());

        // include
        require $template_path;

        // trigger action after render
        do_action('Bond/loaded/' . $dir_name . '/' . $found);
    }

    private function deviceSuffix()
    {
        static $suffix = null;

        if (!$suffix) {
            if (app()->isMobile()) {
                $suffix = '-mobile';
            } elseif (app()->isTablet()) {
                $suffix = '-tablet';
            } else {
                $suffix = '-desktop';
            }
        }
        return $suffix;
    }

    /**
     * Finds a matching template file.
     */
    protected function find($base_name, $dir_name): ?array
    {
        if (empty($base_name) || empty($dir_name)) {
            return null;
        }

        // get file list
        $files = self::$directories[$dir_name];

        // look for possible matches
        foreach ($this->lookup_order as $name) {
            $lookup = [
                $base_name . '-' . $name . $this->deviceSuffix(),
                $base_name . '-' . $name,
            ];
            foreach ($lookup as $file_name) {
                if (isset($files[$file_name])) {
                    return [$file_name, $files[$file_name]];
                }
            }
        }

        // fall back to base_name
        $lookup = [
            $base_name . $this->deviceSuffix(),
            $base_name,
        ];
        foreach ($lookup as $file_name) {
            if (isset($files[$file_name])) {
                return [$file_name, $files[$file_name]];
            }
        }

        return null;
    }

    /**
     * Scan and store all files within the specified directory.
     *
     * @param string $dir_name
     */
    protected function scanDir($dir_name)
    {
        if (!$dir_name || isset(self::$directories[$dir_name])) {
            return;
        }

        self::$directories[$dir_name] = $this->scanDirHelper($dir_name);
    }

    protected function scanDirHelper($dir_name): array
    {
        $result = [];
        $base_path = app()->viewsPath()
            . DIRECTORY_SEPARATOR . $dir_name;

        if (is_dir($base_path)) {
            $dir = new \RecursiveDirectoryIterator(
                $base_path,
                \RecursiveDirectoryIterator::SKIP_DOTS
            );
            $iterator = new \RecursiveIteratorIterator($dir);

            foreach ($iterator as $file) {
                $extension = $file->getExtension();
                $file_name = $file->getBasename('.' . $extension);
                $sub_dirs = trim(str_replace($base_path, '', $file->getPath()), '/');
                $file_name_compound = ($sub_dirs ? $sub_dirs . '/' : '') . $file_name;

                $result[$file_name_compound] = $file->getRealPath();
            }
        }
        return $result;
    }

    /**
     * The rule of this ordering is: from the most specific to the least.
     * Most of the default WP Template Hierarchy is the same, but not all is followed.
     *
     * For the full example of our lookup order plesase follow to:
     *
     * For the default WP hierarchy follow to:
     * http://codex.wordpress.org/Template_Hierarchy
     *
     * @param WP_Query $wp_query
     * @return array
     */
    protected function defineLookupOrder(\WP_Query $wp_query)
    {
        $result = [];

        if (!$wp_query) {
            return $result;
        }

        // prepare vars
        $post = !empty($wp_query->posts) ? $wp_query->posts[0] : false;
        $post_type = $post ? $post->post_type : false;
        $post_slug = $post ? $post->post_name : false;
        $query_post_type = $wp_query->query_vars['post_type'];

        if (is_array($query_post_type)) {
            // it's not usual to have multiple post types on a rewrite rule
            // but even if there is, it's extremely inconsistent to rely on
            // a template name with multiple post types
            // if that's the case, the user will have to alter the template
            // order manually

            $query_post_type = false;
        }

        // Password Protected
        if ($post && \post_password_required($post)) {
            $result[] = 'password-protected';
            // maybe add to more levels, leave this for last
            // just duplicate the final array with this suffix
        }

        // start the template hierarchy build up

        if ($wp_query->is_404()) {

            // 404-[post-type]
            // 404

            if ($query_post_type) {
                $result[] = '404-' . $query_post_type;
            }

            $result[] = '404';
        } elseif ($wp_query->is_search()) {

            // search
            // archive

            $result[] = 'search';
            $result[] = 'archive';
        } elseif ($wp_query->is_front_page()) {

            // if is page on front:
            // front-page
            // page
            // singular

            // if is posts on front:
            // front-page
            // home
            // archive-[post-type]
            // [post-type]
            // archive

            $result[] = 'front-page';

            if ($post_type) {
                if ($post_type !== 'page') {
                    $result[] = 'home';
                    $result[] = 'archive-' . $post_type;
                    $result[] = $post_type;
                    $result[] = 'archive';
                } else {
                    $result[] = 'page';
                    $result[] = 'singular';
                }
            }
        } elseif ($wp_query->is_home()) {

            // home
            // archive-[post-type]
            // [post-type]
            // archive

            $result[] = 'home';

            if ($post_type) {
                $result[] = 'archive-' . $post_type;
                $result[] = $post_type;
                $result[] = 'archive';
            }
            // for now this is not needed, test more
            // } elseif ($wp_query->is_post_type_archive()) {

            //     $result[] = 'archive-'.$query_post_type;
            //     $result[] = $query_post_type;
            //     $result[] = 'archive';
        } elseif ($wp_query->is_author()) {

            // author-[user-login]
            // author-[user-nicename]
            // author
            // archive

            if ($author = get_userdata($post->post_author)) {
                $result[] = 'author-' . $author->data->user_login;

                if ($author->data->user_login !== $author->data->user_nicename) {
                    $result[] = 'author-' . $author->data->user_nicename;
                }
            }

            $result[] = 'author';
            $result[] = 'archive';
        } elseif ($wp_query->is_tax() || $wp_query->is_tag() || $wp_query->is_category()) {

            // taxonomy-[taxonomy]-[term-slug]
            // taxonomy-[taxonomy]
            // taxonomy-[post-type]
            // taxonomy
            // archive-[post-type]
            // [post-type]
            // archive

            $term = get_queried_object();

            if (!empty($term->slug)) {
                $result[] = 'taxonomy-' . $term->taxonomy . '-' . $term->slug;
                $result[] = 'taxonomy-' . $term->taxonomy;
            }

            if ($query_post_type) {
                $result[] = 'taxonomy-' . $query_post_type;
            }

            $result[] = 'taxonomy';

            if ($query_post_type) {
                $result[] = 'archive-' . $query_post_type;
                $result[] = $query_post_type;
            }

            $result[] = 'archive';
        } elseif ($wp_query->is_date()) {

            // date-[post-type]
            // date
            // archive-[post-type]
            // [post-type]
            // archive

            if ($query_post_type) {
                $result[] = 'date-' . $query_post_type;
            }

            $result[] = 'date';

            if ($query_post_type) {
                $result[] = 'archive-' . $query_post_type;
                $result[] = $query_post_type;
            }

            $result[] = 'archive';
        } elseif ($wp_query->is_archive()) {

            // archive-[post-type]
            // [post-type]
            // archive

            if ($query_post_type) {
                $result[] = 'archive-' . $query_post_type;
                $result[] = $query_post_type;
            }

            $result[] = 'archive';
        } elseif ($wp_query->is_page()) {

            // page-[parent-slug]-[post-slug]
            // page-[post-slug]
            // [page-template-name]
            // page
            // singular

            if ($post->post_parent) {
                if ($parent_slug = Query::slug($post->post_parent)) {
                    $result[] = 'page-' . $parent_slug . '-' . $post_slug;
                }
            }

            $result[] = 'page-' . $post_slug;

            // page templates can have their unique names, let's add them before the fallback
            if ($template = Query::pageTemplateName($post->ID)) {
                $result[] = $template;
            }

            $result[] = 'page';
            $result[] = 'singular';
        } elseif ($wp_query->is_attachment()) {

            // single-attachment-[slugfied-long-mime-type]
            // single-attachment-[slugfied-short-mime-type]
            // single-attachment
            // attachment
            // single
            // singular

            // slugfied-long-mime-type = image-jpeg
            // slugfied-short-mime-type = jpeg

            if (!empty($post->post_mime_type)) {

                $result[] = 'single-attachment-' . Str::slug($post->post_mime_type);

                $mime = explode('/', $post->post_mime_type);
                if (count($mime) > 1) {
                    $result[] = 'single-attachment-' . Str::slug($mime[1]);
                }
                $result[] = 'single-attachment-' . $mime[0];
            }

            $result[] = 'single-attachment';
            $result[] = 'attachment';
            $result[] = 'single';
            $result[] = 'singular';
        } elseif ($wp_query->is_single()) {

            // single-[post-type]-[post-slug]
            // single-[post-type]
            // [post-type]
            // single
            // singular

            $result[] = 'single-' . $post_type . '-' . $post_slug;
            $result[] = 'single-' . $post_type;
            $result[] = $post_type;
            $result[] = 'single';
            $result[] = 'singular';
        }

        // everything is handled, allow a filter and go
        $result = \apply_filters('Bond/lookup_order', $result);

        return $result;
    }
}