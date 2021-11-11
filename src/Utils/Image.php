<?php

namespace Bond\Utils;



class Image
{
    protected static array $sizes = [
        'thumbnail' => [300, 0],
        'medium' => [300, 0],
        'medium_large' => [0, 0],
        'large' => [0, 0],
    ];

    public static function setSizes(array $sizes)
    {
        static::$sizes = $sizes;
    }


    // TODO later allow customization
    protected static array $media_sizes = [
        [
            'name' => 'xxxl',
            'minWidth' => 1720,
        ],
        [
            'name' => 'xxl',
            'minWidth' => 1480,
        ],
        [
            'name' => 'xl',
            'minWidth' => 1200,
        ],
        [
            'name' => 'lg',
            'minWidth' => 992,
        ],
        [
            'name' => 'md',
            'minWidth' => 768,
        ],
        [
            'name' => 'sm',
            'minWidth' => 576,
        ],
        [
            'name' => 'w428', // iphone pro max
            'minWidth' => 428,
        ],
        [
            'name' => 'w414', // iphone plus / 8
            'minWidth' => 414,
        ],
        [
            'name' => 'w390', // iphone pro
            'minWidth' => 390,
        ],
        [
            'name' => 'w375', // iphone mini
            'minWidth' => 375,
        ],
    ];


    /*
    $tag = Image::pictureTag(
        $image,
        'medium'
    );
    $tag = Image::pictureTag(
        [
            'default' => $image_mobile,
            'md' => $image_desktop,
        ],
        [
            'default' => 'medium_mobile_crop',
            'md' => 'medium,
        ],
    );
    */
    public static function pictureTag(
        $image,
        $size = 'thumbnail',
        bool $with_caption = false
    ): string {

        // just basic protection
        // still will error out if the default image is not set
        if (empty($image)) {
            return '';
        }

        // allows to set a different image per media breakpoint
        if (is_numeric($image) || is_string($image)) {
            $image = [
                'default' => (int) $image,
            ];
        }

        // auto set the sizes array
        if (is_string($size)) {
            $size = [
                'default' => $size,
            ];
            foreach (static::$media_sizes as $media) {

                $s = $size['default'] . '_' . $media['name'];

                if (isset(static::$sizes[$s])) {
                    $size[$media['name']] = $s;
                }
            }
        }

        // find current values, the first
        $current_image = null;
        $current_size = null;
        foreach (static::$media_sizes as $media) {
            $name = $media['name'];

            if (!$current_image && !empty($image[$name])) {
                $current_image = $image[$name];
            }
            if (!$current_size && !empty($size[$name])) {
                $current_size = $size[$name];
            }

            if ($current_image && $current_size) {
                break;
            }
        }
        if (!$current_image) {
            $current_image = $image['default'];
        }
        if (!$current_size) {
            $current_size = $size['default'];
        }
        // TODO maybe throw error if not found


        // responsive sizes
        $responsive_sizes = [];

        foreach (static::$media_sizes as $media) {
            $name = $media['name'];

            // nothing to do, skip
            if (empty($size[$name]) && empty($image[$name])) {
                continue;
            }

            // update current if needed
            if (!empty($image[$name])) {
                $current_image = $image[$name];
            }
            if (!empty($size[$name])) {
                $current_size = $size[$name];
            }

            // auto retina
            $retina_sizes = (array) $current_size;

            if (
                count($retina_sizes) === 1
                && isset(static::$sizes[$retina_sizes[0] . '_2x'])
            ) {
                $retina_sizes[] = $retina_sizes[0] . '_2x';
            }

            // assemble the media rule
            $rule = isset($media['minWidth'])
                ? 'min-width: ' . $media['minWidth'] . 'px'
                : '';

            $responsive_sizes[] = [
                'rule' => $rule,
                'size' => $retina_sizes,
                'image' => $current_image,
            ];
        }

        // default size
        $retina_sizes = (array) $size['default'];

        // auto retina it too
        if (
            count($retina_sizes) === 1
            && isset(static::$sizes[$retina_sizes[0] . '_2x'])
        ) {
            $retina_sizes[] = $retina_sizes[0] . '_2x';
        }

        // get the tag
        $tag = self::pictureTagHelper(
            $image['default'],
            $retina_sizes,
            $responsive_sizes
        );

        // caption, if requested
        if ($tag && $with_caption) {
            $caption = static::caption($image['default']);
            if (!empty($caption)) {
                $tag = '<figure>'
                    . $tag
                    . '<figcaption>'
                    . Str::br($caption)
                    . '</figcaption>'
                    . '</figure>';
            }
        }

        return $tag;
    }

    // TODO improve this, look into t/g JS version
    private static function pictureTagHelper(
        $image_id,
        $default_size,
        array $responsive_sizes = null,
        array $options = []
    ): string {
        // short-cirtcuit for animated gifs
        if (\get_post_mime_type($image_id) === 'image/gif') {
            $default_size = 'full';
            $responsive_sizes = null;
        }

        $default_size = (array) $default_size;
        $with_site_url = $options['site_url'] ?? true;

        // get image url
        $default_source = self::source(
            $image_id,
            $default_size[0],
            $with_site_url
        );

        // exit early
        if (empty($default_source[0])) {
            return '';
        }


        // form the picture tag
        $result = '<picture>';

        if (!empty($responsive_sizes)) {

            foreach ($responsive_sizes as $r) {

                if (empty($r['image'])) {
                    $r['image'] = $image_id;
                }
                $size = (array) $r['size'];

                $source = self::source(
                    $r['image'],
                    $size[0],
                    $with_site_url
                );
                if (empty($source[0])) {
                    continue;
                }

                // url
                $result .= '<source srcset="' . $source[0];

                if (count($size) > 1) {
                    $result .= ' 1x, ' . self::url(
                        $r['image'],
                        $size[1],
                        $with_site_url
                    ) . ' 2x';
                }
                $result .= '"';

                // size
                $result .= ' width="' . $source[1] . '" height="' . $source[2] . '"';

                // media
                $result .= ' media="(' . trim(trim($r['rule'], ')'), '(') . ')">';
            }
        }

        // default image
        if (count($default_size) > 1) {
            $result .= '<img srcset="' . $default_source[0] . ' 1x, ' . self::url(
                $image_id,
                $default_size[1],
                $with_site_url
            ) . ' 2x"';
        } else {
            $result .= '<img srcset="' . $default_source[0] . '"';
        }

        // size
        $result .= ' width="' . $default_source[1] . '" height="' . $default_source[2] . '"';


        //the alt
        if (empty($options['no_alt'])) {
            $result .= ' alt="' . Str::clean(self::alt($image_id, true)) . '"';
        }
        $result .= '>';

        // close picture tag
        $result .= '</picture>';

        return $result;
    }


    public static function imageTags(
        array $image_ids,
        $size = 'thumbnail',
        array $options = []
    ): string {

        $res = '';
        foreach ($image_ids as $id) {
            $res .= static::imageTag($id, $size, $options);
        }
        return $res;
    }

    /**
     *
     *
     * @param int $image_id
     * @param string|array $size
     * @param array $options
     * @return string
     */
    public static function imageTag(
        $image_id,
        $size = 'thumbnail',
        array $options = []
    ): string {

        // short-cirtcuit for animated gifs
        if (!empty($options['animated-gifs'])) {
            if (\get_post_mime_type($image_id) === 'image/gif') {
                $size = 'full';
            }
        }

        $size = (array) $size;
        $with_site_url = $options['site_url'] ?? true;

        // get image source
        $src = self::source(
            $image_id,
            $size[0],
            $with_site_url
        );

        // exit early
        if (!$src) {
            return '';
        }

        // form the image tag
        $result = '<img';

        // the src
        if (count($size) > 1) {
            $result .= ' srcset="' . $src[0] . ' 1x, ' . self::url(
                $image_id,
                $size[1],
                $with_site_url
            ) . ' 2x"';
        } elseif (!empty($options['no_src'])) {
            $result .= ' data-src="' . $src[0] . '"';
        } else {
            $result .= ' src="' . $src[0] . '"';
        }

        // the dimensions
        if (empty($options['no_size'])) {
            $result .= ' width=' . $src[1] . ' height=' . $src[2];
        }

        // classes
        $classes = !empty($options['class']) ? (array) $options['class'] : [];

        if (!empty($classes)) {
            $result .= ' class="' . implode(' ', $classes) . '"';
        }

        //the alt
        if (empty($options['no_alt'])) {
            $result .= ' alt="' . Str::clean(self::alt($image_id, true)) . '"';
        }

        // attributes
        $result .= self::attributesToString($options['attributes'] ?? []);

        //close tag
        $result .= '>';

        return $result;
    }



    public static function isVertical($image_id): bool
    {
        $r = self::sizeRatio($image_id);
        return $r && $r < 1;
    }

    public static function isVerticalOrSquare($image_id): bool
    {
        $r = self::sizeRatio($image_id);
        return $r && $r <= 1;
    }

    public static function isHorizontal($image_id): bool
    {
        $r = self::sizeRatio($image_id);
        return $r && $r > 1;
    }

    public static function isWide($image_id, float $wide_index = 1.7): bool
    {
        $r = self::sizeRatio($image_id);
        return $r && $r >= $wide_index;
    }

    public static function isSquare($image_id): bool
    {
        $r = self::sizeRatio($image_id);
        return $r && $r === 1;
    }

    public static function sizeRatio($image_id): float
    {
        $path = \get_attached_file($image_id);
        if (!file_exists($path)) {
            return 0;
        }
        $image_size = getimagesize($path);

        return empty($image_size)
            ? 0
            : $image_size[0] / $image_size[1];
    }


    public static function url(
        $image_id,
        $size,
        $site_url = true
    ): string {
        $src = self::source($image_id, $size, $site_url);
        return $src ? $src[0] : '';
    }

    public static function urls(
        array $image_ids,
        $size,
        $site_url = true
    ): array {
        $result = [];
        foreach ($image_ids as $image_id) {
            if ($url = self::url($image_id, $size, $site_url)) {
                $result[] = $url;
            }
        }
        return $result;
    }


    public static function source(
        $image_id,
        $size = null,
        $site_url = true
    ): ?array {

        // skip if false
        if (!$image_id) {
            return null;
        }

        // default
        $image = false;
        $is_vertical = false;

        // alternate vertical size image
        $v_size = null;

        if (is_array($size) && count($size) > 1) {
            $v_size = $size[1];
            $size = $size[0];
        }

        // handle the vertical size alternation
        if ($v_size) {

            // get full size image
            $image_size = getimagesize(\get_attached_file($image_id));

            //if image height is larger than the width return the vertical image
            if ($image_size && $image_size[1] > $image_size[0]) {
                $image = self::imageDownsizeHelper($image_id, $v_size);
                $is_vertical = true;
            }
        }

        // get default size
        if (!$image && !$is_vertical) {
            $image = self::imageDownsizeHelper($image_id, $size);
        }

        // without site url
        if ($image && !$site_url) {
            $image[0] = parse_url($image[0], PHP_URL_PATH);
        }

        return $image ?: null;
    }



    public static function caption($image): string
    {
        $att = Cast::post($image);
        if (!$att) {
            return '';
        }
        return (string) ($att->caption ?: $att->post_excerpt);
    }


    public static function alt($image): string
    {
        $att = Cast::post($image);
        if (!$att) {
            return '';
        }
        return (string) ($att->alt ?: \get_post_meta($att->ID, '_wp_attachment_image_alt', true));
    }


    /**
     *
     * @param int $image_id
     * @param string $size
     * @return array|false
     */
    private static function imageDownsizeHelper($image_id, $size)
    {
        $image = \image_downsize($image_id, $size);

        // create missing image sizes
        if ($image && (int) $image[1] <= 1 && (int) $image[2] <= 1) {
            // same lock as used in wp_maybe_generate_attachment_metadata()
            $regeneration_lock = 'wp_generating_att_' . $image_id;

            if (!\get_transient($regeneration_lock)) {
                // get file
                $file = \get_attached_file($image_id);
                if (file_exists($file)) {
                    // add lock
                    \set_transient($regeneration_lock, $file);

                    // incluce required functions
                    require_once ABSPATH . 'wp-admin/includes/image.php';

                    // regenerate image sizes
                    \wp_update_attachment_metadata($image_id, \wp_generate_attachment_metadata($image_id, $file));

                    // load again
                    $image = \image_downsize($image_id, $size);

                    // delete lock
                    \delete_transient($regeneration_lock);
                }
            }
        }

        return $image;
    }


    private static function attributesToString(array $attributes, $key_prefix = null): string
    {
        $result = '';
        foreach ($attributes as $key => $value) {

            if ($key_prefix) {
                $key = $key_prefix . $key;
            }

            $key = Str::kebab($key);

            if ($value === true) {
                $result .= ' ' . $key;
            } elseif ($value === false) {
                continue;
            } else {
                $result .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
            }
        }
        return $result;
    }




    public static function findWpImages($post_content): array
    {
        $matches = [];

        if (!empty($post_content)) {

            if (preg_match_all(
                '/<img.*?wp-image-(\d*).*?(>|<\/img>)/',
                $post_content,
                $matches
            )) {
                if (!empty($matches[1])) {
                    return array_map('intval', (array) $matches[1]);
                }
            }
        }
        return $matches;
    }
}
