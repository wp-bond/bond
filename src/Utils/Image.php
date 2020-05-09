<?php

namespace Bond\Utils;



class Image
{

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
        $size,
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
            return str_replace(config()->url(), '', $image);
        }

        return $image ?: null;
    }



    /**
     *
     * @param int $image_id
     * @param boolean $with_fallback
     * @return string
     */
    public static function caption($image_id, $with_fallback = false): string
    {
        $attachment = Cast::wpPost($image_id);
        if (empty($attachment)) {
            return '';
        }

        $result = $attachment->post_excerpt;

        if ($with_fallback && empty($result)) {

            // try the image description
            $result = $attachment->post_content;

            // try the image alt field
            if (empty($result)) {
                $result = \get_post_meta($image_id, '_wp_attachment_image_alt', true);
            }

            // finally, use the image title
            if (empty($result)) {
                $result = $attachment->post_title;
            }
        }

        return $result;
    }


    /**
     *
     * @param int $image_id
     * @param boolean $with_fallback
     * @return string
     */
    public static function alt($image_id, $with_fallback = false): string
    {
        $result = \get_post_meta($image_id, '_wp_attachment_image_alt', true);

        if ($with_fallback && empty($result)) {
            $attachment = \get_post($image_id);

            // try the description
            $result = $attachment->post_content;

            // try the caption
            if (empty($result)) {
                $result = $attachment->post_excerpt;
            }

            // finally, use the title
            if (empty($result)) {
                $result = $attachment->post_title;
            }
        }

        return $result;
    }




    public static function isVertical($image_id)
    {
        $r = self::sizeRatio($image_id);
        return $r && $r < 1;
    }

    public static function isHorizontal($image_id)
    {
        $r = self::sizeRatio($image_id);
        return $r && $r > 1;
    }

    public static function isWide($image_id, $wide_index = 1.7)
    {
        $r = self::sizeRatio($image_id);
        return $r && $r >= $wide_index;
    }

    public static function isSquare($image_id)
    {
        $r = self::sizeRatio($image_id);
        return $r && $r === 1;
    }

    public static function sizeRatio($image_id)
    {
        $path = \get_attached_file($image_id);
        if (!file_exists($path)) {
            return 0;
        }
        $image_size = getimagesize($path);

        return empty($image_size) ? 0 : $image_size[0] / $image_size[1];
    }


    /**
     *
     *
     * @param int $image_id
     * @param string|array $size
     * @param array $options
     * @return string
     */
    public static function imageTag($image_id, $size, array $options = []): string
    {
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


    /*
$tag = Image::pictureTag(
    $mobile_image_id,
    [FULL_PAGE_MOBILE, FULL_PAGE_MOBILE.'_retina'],
    [
        [
            'rule' => 'min-width: 960px',
            'size' => [FULL_PAGE, FULL_PAGE.'_retina'],
            'image' => $image_id,
        ],
    ],
    ['class' => 'front-page-bg']
);
 */
    public static function pictureTag(
        $image_id,
        $default_size,
        array $responsive_sizes = null,
        array $options = []
    ) {
        // short-cirtcuit for animated gifs
        if (\get_post_mime_type($image_id) === 'image/gif') {
            $default_size = 'full';
            $responsive_sizes = null;
        }

        $default_size = (array) $default_size;
        $with_site_url = $options['site_url'] ?? true;

        // get image url
        $default_url = self::url(
            $image_id,
            $default_size[0],
            $with_site_url
        );

        // exit early
        if (!$default_url) {
            return '';
        }

        // data string
        $data_string = '';
        $data = [];

        if (!empty($options['data_size'])) {

            $img_src = self::source(
                $image_id,
                $default_size[0],
                $with_site_url
            );

            if (!empty($img_src[0]) && !empty($img_src[1]) && !empty($img_src[2])) {
                $data['width'] = $img_src[1];
                $data['height'] = $img_src[2];
            }
        }

        $data_string = self::attributesToString($data, 'data-');


        // form the picture tag
        $result = '<picture';
        if (!empty($options['class'])) {
            $result .= ' class="' . implode(' ', (array) $options['class']) . '"';
        }
        $result .= $data_string;
        $result .= '>';

        if (!empty($responsive_sizes)) {

            foreach ($responsive_sizes as $r) {

                if (empty($r['image'])) {
                    $r['image'] = $image_id;
                }
                $size = (array) $r['size'];

                $result .= '<source srcset="' . self::url(
                    $r['image'],
                    $size[0],
                    $with_site_url
                );

                if (count($size) > 1) {
                    $result .= ' 1x, ' . self::url(
                        $r['image'],
                        $size[1],
                        $with_site_url
                    ) . ' 2x';
                }
                $result .= '"';

                $result .= ' media="(' . trim(trim($r['rule'], ')'), '(') . ')">';
            }
        }

        // default image
        if (count($default_size) > 1) {
            $result .= '<img srcset="' . $default_url . ' 1x, ' . self::url(
                $image_id,
                $default_size[1],
                $with_site_url
            ) . ' 2x"';
        } else {
            $result .= '<img srcset="' . $default_url . '"';
        }

        //the alt
        if (empty($options['no_alt'])) {
            $result .= ' alt="' . Str::clean(self::alt($image_id, true)) . '"';
        }
        $result .= '>';

        // close picture tag
        $result .= '</picture>';

        return $result;
    }


    /**
     *
     * @param int $image_id
     * @param string $size
     * @return array
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

            $key = Str::slug($key);

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
