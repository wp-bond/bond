<?php

namespace Bond\Services;

use Bond\Support\Fluent;
use Bond\Support\FluentList;
use Bond\Utils\Query;
use Bond\Utils\Str;
use WP_Query;

/**
 * Template loader utility that builds upon the WP template hierarchy, allowing a logical and compartmentalised folder/file organization.
 *
 * Besides loading template files, it offers the same approach to filter data to the templates through the WP action & filter hooks, allowing a separation of data logic and the template files.
 *
 * This class does not override the default WordPress template loader, so you can safely mix and match both approaches.
 *
 */
class View extends Fluent implements ServiceInterface
{
    protected array $directories = [];
    protected string $templates_dir = 'templates';
    protected string $partials_dir = 'partials';

    protected array $lookup_order = [];
    protected bool $do_actions = false;


    public function config(
        ?bool $enabled = null,
        ?bool $use_theme = null,
        ?array $lookup_folders = null,
        ?bool $auto_set_order = null,
        ?bool $do_actions = null,
        ?string $templates_dir = null,
        ?string $partials_dir = null,
        ?array $data = null,
    ) {

        if ($use_theme && $enabled) {
            if (!isset($lookup_folders)) {
                $lookup_folders = [app()->viewsPath()];
            }
            if (!isset($auto_set_order)) {
                $auto_set_order = true;
            }
            if (!isset($do_actions)) {
                $do_actions = true;
            }
        }

        if (isset($lookup_folders)) {
            foreach ($lookup_folders as $folder) {
                $this->addLookupFolder($folder);
            }
        }

        if (isset($templates_dir)) {
            $this->setTemplatesDir($templates_dir);
        }
        if (isset($partials_dir)) {
            $this->setPartialsDir($partials_dir);
        }

        if ($auto_set_order) {
            $this->autoSetOrder();
        }

        if (isset($do_actions)) {
            $this->do_actions = $do_actions;
        }

        // add any arbitrary values into the View
        if (isset($data)) {
            $this->add($data);
        }

        // enable
        if (isset($enabled)) {
            if ($enabled) {
                $this->enable();
            } else {
                $this->disable();
            }
        }
    }

    public function enable()
    {
        if (!$this->enabled) {
            $this->enabled = true;

            // actions
            if ($this->do_actions) {
                $this->addActions();
            }
        }
    }

    public function disable()
    {
        if ($this->enabled) {
            $this->enabled = false;

            // actions
            $this->removeActions();
        }
    }

    public function addLookupFolder(string $path, ?int $index = null)
    {
        $files = $this->scanDir($path);
        if (empty($files)) {
            return;
        }

        if ($index === null) {
            $this->directories[] = $files;
        } else {
            array_splice($this->directories, $index, 0, [$files]);
        }
    }

    protected function addActions()
    {
        if (\did_action('init')) {
            $this->triggerInitActions();
        } else {
            \add_action('init', [$this, 'triggerInitActions'], 20);
        }
        if (\did_action('wp')) {
            $this->triggerReadyActions();
        } else {
            \add_action('wp', [$this, 'triggerReadyActions'], 20);
        }
        if (\did_action('template_redirect')) {
            $this->triggerRedirectActions();
        } else {
            \add_action('template_redirect', [$this, 'triggerRedirectActions'], 20);
        }
    }

    protected function removeActions()
    {
        \remove_action('init', [$this, 'triggerInitActions'], 20);
        \remove_action('wp', [$this, 'triggerReadyActions'], 20);
        \remove_action('template_redirect', [$this, 'triggerRedirectActions'], 20);
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
        $this->doActions('init');
    }

    public function triggerReadyActions()
    {
        $this->doActions('ready');
    }

    public function triggerRedirectActions()
    {
        $this->doActions('template_redirect');
    }

    protected function doActions(string $action)
    {
        if (\did_action('Bond/' . $action)) {
            return;
        }

        \do_action('Bond/' . $action);
        \do_action('Bond/' . $action . $this->deviceSuffix());

        foreach (array_reverse($this->lookup_order) as $name) {
            \do_action('Bond/' . $action . '/' . $name);
            \do_action('Bond/' . $action . '/' . $name . $this->deviceSuffix());
        }
    }

    /**
     * Gets order which the templates will be looked up.
     */
    public function getOrder(): array
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

    public function hasOrder($order): bool
    {
        return in_array($order, $this->lookup_order);
    }

    /**
     * Sets the order which the templates will be looked up.
     */
    public function setOrder(bool|array|WP_Query $with)
    {
        if (!$with) {
            return;
        }
        if (is_array($with) || is_string($with)) {
            $this->lookup_order = (array) $with;
        } elseif (is_a($with, 'WP_Query')) {
            $this->lookup_order = $this->defineLookupOrder($with);
        } elseif (!is_admin()) {
            $this->autoSetOrder();
        }
    }

    /**
     * Automatically sets the template lookup order based on the global $wp_query object.
     * If $wp_query is not set yet (before 'wp' action hook) it will hook into the 'wp' action with
     * priority 1, to set the order as soon as possible and not interfere with other actions.
     */
    public function autoSetOrder()
    {
        if (\did_action('wp')) {
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
     */
    public function getTemplatesDir(): string
    {
        return $this->templates_dir;
    }

    /**
     * Set default templates sub-directory, relative to lookup folder.
     */
    public function setTemplatesDir(string $dir_name)
    {
        $this->templates_dir = trim($dir_name, '/');
    }

    /**
     * Get default partials sub-directory.
     */
    public function getPartialsDir(): string
    {
        return $this->partials_dir;
    }

    /**
     * Set default partials sub-directory, relative to lookup folder.
     */
    public function setPartialsDir(string $dir_name)
    {
        $this->partials_dir = trim($dir_name, '/');
    }

    /**
     * Load a matching file inside the current templates dir.
     * The provided data is the View itself.
     * Returns boolean if the template was found.
     */
    public function template(string $base_name): bool
    {
        return $this->load(
            $this->templates_dir . '/' . $base_name,
            $this
        );
    }

    /**
     * Load a matching file inside the current partials dir.
     * Data may be provided if needed otherwide it's empty.
     * Returns boolean if the template was found.
     */
    public function partial(string $name, $data = null): bool
    {
        return $this->load(
            $this->partials_dir . '/' . $name,
            $data
        );
    }

    /**
     * Load a template file matching the current lookup order.
     * Returns boolean if the template was found.
     */
    public function load(string $template_name, $data = null): bool
    {
        // find the template file
        list($found, $template_path) = $this->find($template_name);

        // skip if not found
        if (!$template_path) {
            return false;
        }

        // trigger action before render
        if ($this->do_actions) {
            \do_action('Bond/load/' . $found);
        }

        // cast as Fluent/FluentList and run
        $this->fluent($data)->run($template_path);

        // trigger action after render
        if ($this->do_actions) {
            \do_action('Bond/loaded/' . $found);
        }

        return true;
    }



    // helpers


    protected function fluent($data): Fluent|FluentList
    {
        if ($data === null) {
            return new Fluent();
        }
        if ($data instanceof Fluent || $data instanceof FluentList) {
            return $data;
        }
        if (is_array($data) && array_is_list($data)) {
            return new FluentList($data);
        }
        if (is_array($data) || is_object($data)) {
            return new Fluent($data);
        }
        return new Fluent(['content' => $data]);
    }

    protected function deviceSuffix()
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
    protected function find(string $template): ?array
    {
        if (empty($template)) {
            return null;
        }

        // look for possible matches
        foreach ($this->lookup_order as $name) {
            $lookup = [
                $template . '-' . $name . $this->deviceSuffix(),
                $template . '-' . $name,
            ];
            foreach ($lookup as $file_name) {
                foreach ($this->directories as $files) {
                    if (isset($files[$file_name])) {
                        return [$file_name, $files[$file_name]];
                    }
                }
            }
        }

        // fall back to straight template name
        $lookup = [
            $template . $this->deviceSuffix(),
            $template,
        ];
        foreach ($lookup as $file_name) {
            foreach ($this->directories as $files) {
                if (isset($files[$file_name])) {
                    return [$file_name, $files[$file_name]];
                }
            }
        }

        return null;
    }

    /**
     * Scan all files within the specified directory.
     */
    protected function scanDir(string $path): array
    {
        // TODO should we cache this? Test the performance
        // Maybe only if not isDevelopment()
        // How to invalidate when a template is added? Maybe never, and require the user to manually clear the cache?

        $files = [];

        if (is_dir($path)) {
            $dir = new \RecursiveDirectoryIterator(
                $path,
                \RecursiveDirectoryIterator::SKIP_DOTS
            );
            $iterator = new \RecursiveIteratorIterator($dir);

            foreach ($iterator as $file) {
                $extension = $file->getExtension();
                $file_name = $file->getBasename('.' . $extension);

                // skip .DS_Store
                if ($file_name[0] === '.') {
                    continue;
                }

                // assemble lokup name
                $sub_dirs = trim(str_replace($path, '', $file->getPath()), '/');
                $file_name_compound = ($sub_dirs ? $sub_dirs . '/' : '') . $file_name;

                $files[$file_name_compound] = $file->getRealPath();
            }
        }
        // dd($files);
        return $files;
    }

    /**
     * The rule of this ordering is: from the most specific to the least.
     * Most of the default WP Template Hierarchy is the same, but not all is followed.
     *
     * For the full example of our lookup order plesase follow to:
     *
     * For the default WP hierarchy follow to:
     * http://codex.wordpress.org/Template_Hierarchy
     */
    protected function defineLookupOrder(WP_Query $query): array
    {
        $result = [];

        if (!$query) {
            return $result;
        }

        // prepare vars
        $_post = !empty($query->posts) ? $query->posts[0] : null;

        // if post is missing, but is acknoleged as singular
        // let look into GET parameter
        if (
            !$_post
            && !empty($_GET['p'])
            && $query->is_singular()
        ) {
            $_post = get_post($_GET['p']);

            // Let's take to opportunity and fix a WordPress issue
            // of draft posts being missing on wp_query
            if ($query->is_main_query()) {
                global $post;
                if (empty($post)) {
                    $post = $_post;
                }

                global $wp_query;
                if (empty($wp_query->queried_object)) {
                    $wp_query->queried_object = $_post;
                }
            }
        }

        $post_type = $_post ? $_post->post_type : null;
        $post_slug = $_post ? $_post->post_name : null;
        $query_post_type = $query->query_vars['post_type'] ?? '';

        if (is_array($query_post_type)) {
            // it's not usual to have multiple post types on a rewrite rule
            // but even if there is, it's extremely inconsistent to rely on
            // a template name with multiple post types
            // if that's the case, the user will have to alter the template
            // order manually

            $query_post_type = false;
        }

        // Password Protected
        if ($_post && \post_password_required($_post)) {
            $result[] = 'password-protected';
            // maybe add to more levels, leave this for last
            // just duplicate the final array with this suffix
        }

        // start the template hierarchy build up

        if ($query->is_404()) {

            // 404-[post-type]
            // 404

            if ($query_post_type) {
                $result[] = '404-' . $query_post_type;
            }

            $result[] = '404';
        } elseif ($query->is_search()) {

            // search
            // archive

            $result[] = 'search';
            $result[] = 'archive';
        } elseif ($query->is_front_page()) {

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

                    if ($template = Query::pageTemplate($_post->ID, true)) {
                        $result[] = $template;
                    }
                    $result[] = 'page';
                    $result[] = 'singular';
                }
            }
        } elseif ($query->is_home()) {

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
            // } elseif ($query->is_post_type_archive()) {

            //     $result[] = 'archive-'.$query_post_type;
            //     $result[] = $query_post_type;
            //     $result[] = 'archive';
        } elseif ($query->is_author()) {

            // author-[user-login]
            // author-[user-nicename]
            // author
            // archive

            if ($author = get_userdata($_post->post_author)) {
                $result[] = 'author-' . $author->data->user_login;

                if ($author->data->user_login !== $author->data->user_nicename) {
                    $result[] = 'author-' . $author->data->user_nicename;
                }
            }

            $result[] = 'author';
            $result[] = 'archive';
        } elseif ($query->is_tax() || $query->is_tag() || $query->is_category()) {

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
        } elseif ($query->is_date()) {

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
        } elseif ($query->is_archive()) {

            // archive-[post-type]
            // [post-type]
            // archive

            if ($query_post_type) {
                $result[] = 'archive-' . $query_post_type;
                $result[] = $query_post_type;
            }

            $result[] = 'archive';
        } elseif ($query->is_page()) {

            // page-[parent-slug]-[post-slug]
            // page-[post-slug]
            // [page-template-name]
            // page
            // singular

            if ($_post->post_parent) {
                if ($parent_slug = Query::slug($_post->post_parent)) {
                    $result[] = 'page-' . $parent_slug . '-' . $post_slug;
                }
            }

            $result[] = 'page-' . $post_slug;

            // page templates can have their unique names, let's add them before the fallback
            if ($template = Query::pageTemplate($_post->ID, true)) {
                $result[] = $template;
            }

            $result[] = 'page';
            $result[] = 'singular';
        } elseif ($query->is_attachment()) {

            // [page-template-name]-attachment
            // [page-template-name]
            // single-attachment-[slugfied-long-mime-type]
            // single-attachment-[slugfied-short-mime-type]
            // single-attachment
            // attachment
            // single
            // singular

            // slugfied-long-mime-type = image-jpeg
            // slugfied-short-mime-type = jpeg

            if ($template = Query::pageTemplate($_post->ID, true)) {
                $result[] = $template . '-attachment';
                $result[] = $template;
            }
            if (!empty($_post->post_mime_type)) {
                $result[] = 'single-attachment-' . Str::kebab($_post->post_mime_type);

                $mime = explode('/', $_post->post_mime_type);
                if (count($mime) > 1) {
                    $result[] = 'single-attachment-' . Str::kebab($mime[1]);
                }
                $result[] = 'single-attachment-' . $mime[0];
            }

            $result[] = 'single-attachment';
            $result[] = 'attachment';
            $result[] = 'single';
            $result[] = 'singular';
        } elseif ($query->is_single()) {

            // [page-template-name]-[post-type]
            // [page-template-name]
            // single-[post-type]-[post-slug]
            // single-[post-type]
            // [post-type]
            // single
            // singular
            if ($_post) {
                if ($template = Query::pageTemplate($_post->ID, true)) {
                    $result[] = $template . '-' . $post_type;
                    $result[] = $template;
                }
            }
            $result[] = 'single-' . $post_type . '-' . $post_slug;
            $result[] = 'single-' . $post_type;
            $result[] = $post_type;
            $result[] = 'single';
            $result[] = 'singular';
        }

        // everything is handled, allow a filter and go
        if ($this->do_actions) {
            $result = \apply_filters('Bond/lookup_order', $result);
        }

        return $result;
    }
}
