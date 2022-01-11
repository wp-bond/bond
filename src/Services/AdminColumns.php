<?php

namespace Bond\Services;

use Bond\Post;
use Bond\Settings\Language;
use Bond\Settings\Wp;
use Bond\Support\Fluent;
use Bond\Term;
use Bond\User;
use Bond\Utils\Cast;
use Bond\Utils\Image;
use Bond\Utils\Str;

class AdminColumns
{
    private array $columns = [];


    public function config(Fluent $settings)
    {
        if (!Wp::isAdmin()) {
            return;
        }

        if ($settings->enabled) {
            $this->enable();
        }
        if ($settings->enabled === false) {
            $this->disable();
        }
    }

    public function enable()
    {
        // adds some handlers
        $this->addMultilanguageLinksHandler();

        // Styles
        \add_action(
            'admin_head',
            [$this, 'outputStyles']
        );

        // Posts
        \add_action(
            'manage_pages_custom_column',
            [$this, 'handlePostColumn'],
            10,
            2
        );
        \add_action(
            'manage_posts_custom_column',
            [$this, 'handlePostColumn'],
            10,
            2
        );
        \add_action(
            'manage_media_custom_column',
            [$this, 'handlePostColumn'],
            10,
            2
        );

        // Users
        \add_action(
            'manage_users_custom_column',
            [$this, 'handleUserColumn'],
            10,
            3
        );

        // Taxonomies
        // wait until taxonomies are registered
        \add_action('wp_loaded', function () {
            global $wp_taxonomies;

            foreach (array_keys($wp_taxonomies) as $taxonomy) {
                \add_filter(
                    'manage_' . $taxonomy . '_custom_column',
                    [$this, 'handleTaxonomyColumn'],
                    10,
                    3
                );
            }
        });
    }

    public function disable()
    {
        // Styles
        \remove_action(
            'admin_head',
            [$this, 'outputStyles']
        );

        // Posts
        \remove_action(
            'manage_pages_custom_column',
            [$this, 'handlePostColumn'],
            10,
            2
        );
        \remove_action(
            'manage_posts_custom_column',
            [$this, 'handlePostColumn'],
            10,
            2
        );
        \remove_action(
            'manage_media_custom_column',
            [$this, 'handlePostColumn'],
            10,
            2
        );

        // Users
        \remove_action(
            'manage_users_custom_column',
            [$this, 'handleUserColumn'],
            10,
            3
        );

        // Taxonomies
        // wait until taxonomies are registered
        \remove_action('wp_loaded', function () {
            global $wp_taxonomies;

            foreach (array_keys($wp_taxonomies) as $taxonomy) {
                \remove_filter(
                    'manage_' . $taxonomy . '_custom_column',
                    [$this, 'handleTaxonomyColumn'],
                    10,
                    3
                );
            }
        });
    }



    public function addHandler(
        string $name,
        callable $handler,
        string|int $width = 0,
        string $css = null
    ) {
        // only needed on admin
        if (!Wp::isAdmin()) {
            return;
        }
        $this->columns[$name] = compact('handler', 'width', 'css');
    }



    public function handlePostColumn($name, $post_id)
    {
        echo $this->handleColumn($name, Cast::post($post_id));
    }

    public function handleTaxonomyColumn($content, $name, $term_id)
    {
        echo $this->handleColumn($name, Cast::term($term_id));
    }

    public function handleUserColumn($content, $name, $user_id)
    {
        echo $this->handleColumn($name, Cast::user($user_id));
    }

    protected function handleColumn(string $name, $item)
    {
        if (!$item) {
            return '';
        }
        if (!isset($this->columns[$name])) {
            return $this->defaultColumnOutput($item, $name);
        }

        return $this->columns[$name]['handler']($item);
    }



    public function setPostTypeColumns(string $post_type, array $columns)
    {
        // only needed on admin
        if (!Wp::isAdmin()) {
            return;
        }

        if ($post_type === 'attachment') {
            $hook = 'manage_media_columns';
        } else {
            $hook = 'manage_' . $post_type . '_posts_columns';
        }

        \add_filter(
            $hook,
            function ($defaults) use ($columns) {
                return $this->prepareColumns($columns);
            }
        );
    }

    public function setTaxonomyColumns(string $taxonomy, array $columns)
    {
        // only needed on admin
        if (!Wp::isAdmin()) {
            return;
        }
        \add_filter(
            'manage_edit-' . $taxonomy . '_columns',
            function ($defaults) use ($columns) {
                return $this->prepareColumns($columns);
            }
        );
    }

    public function setUsersColumns(array $columns)
    {
        // only needed on admin
        if (!Wp::isAdmin()) {
            return;
        }

        \add_filter(
            'manage_users_columns',
            function ($defaults) use ($columns) {
                return $this->prepareColumns($columns);
            }
        );
    }



    // helpers

    private function prepareColumns(array
    $columns): array
    {
        foreach ($columns as $k => &$title) {
            $title = tx($title, 'admin-columns');
        }

        if (!isset($columns['cb'])) {
            $columns = array_merge([
                'cb' => '<input type="checkbox" />',
            ], $columns);
        }
        return $columns;
    }


    public function outputStyles()
    {
        echo '<style>';
        foreach ($this->columns as $name => $options) {

            // output plain css
            if (!empty($options['css'])) {
                echo $options['css'];
            }

            // set column width
            if (!empty($options['width'])) {
                $width = $options['width'];
                if (is_int($width)) {
                    $width .= 'px';
                }

                echo <<<CSS
                .column-{$name} {
                    width: {$width};
                }
                CSS;
            }
        }
        echo '</style>';
    }


    protected function defaultColumnOutput(Post|Term|User $item, string $column): string
    {
        if ($item instanceof Post) {

            // content
            if ($column === 'content') {
                $content = $item->content();

                return '<a href="' . get_edit_post_link($item->ID) . '">'
                    . ($content ? Str::clean($content, 20) : '—')
                    . '</a>';
            }

            // image
            if ($column === 'image') {
                $image_id = $item->imageId();

                if ($image_id) {
                    return '<a href="' . get_edit_post_link($item->ID) . '">'
                        . Image::imageTag($image_id)
                        . '</a>';
                }
            }
        }

        // boolean
        if (strpos($column, 'is_') === 0) {
            return '<div class="bond-boolean-icon ' . ($item[$column] ? 'green' : '') . '"></div>';
        }

        // caption method
        if (
            $column === 'caption'
            && method_exists($item, 'caption')
        ) {
            return $item->caption();
        }

        // handle by value
        $values = [];

        foreach ((array) $item[$column] as $v) {
            // urls
            if (Str::isUrl($v)) {
                $values[] = '<a href="' . $v . '" target="_blank" rel="noopener noreferrer">' . $v . '</a>';
                continue;
            }

            // else just add the value
            $values[] = $v === '' ? '—' : $v;
        }

        return implode(', ', $values);
    }


    protected function addMultilanguageLinksHandler()
    {
        $name = 'multilanguage_links';

        if (isset($this->columns[$name])) {
            return;
        }

        $this->addHandler(
            $name,

            function (Post|Term|User $item) {
                $res = '<div class="multilanguage-circles">';

                foreach (Language::codes() as $code) {

                    $link = $item->link($code);

                    $res .= $link
                        ? '<a href="' . $link . '" target="_blank">'
                        : '<div>';

                    $res .= '<div class="multilanguage-circle ' . ($item->isDisabled($code) ? 'disabled' : '') . '">'
                        . Language::shortCode($code)
                        . '</div>';

                    $res .= $link
                        ? '</a>'
                        : '</div>';
                }

                $res .= '</div>';
                return $res;
            },

            width: 30 * Language::count() + 5,

            css: <<<CSS
                .multilanguage-circles {
                    display: flex;
                }
                .multilanguage-circle {
                    width: 24px;
                    height: 24px;
                    border: 1px solid #333;
                    color: #000;
                    opacity: 0.7;
                    border-radius: 100%;
                    text-transform: uppercase;
                    text-align: center;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 11px;
                    line-height: 0;
                    flex: 0;
                    flex-basis: 24px;
                    margin-right: 6px;
                }
                .multilanguage-circle.disabled {
                    border-color: red;
                    color: red;
                }
                .multilanguage-circle:hover {
                    background-color: white;
                    opacity: 1;
                    border-color: black;
                }
                .multilanguage-circle.disabled:hover {
                    border-color: red;
                }
                CSS
        );
    }
}
