<?php

namespace Bond\Services;

// Adds missing feature to print alternate language urls
class SitemapRenderer extends \WP_Sitemaps_Renderer
{
    public function get_sitemap_xml($url_list)
    {
        /* [start] Unmodified from WP core */
        $urlset = new \SimpleXMLElement(
            sprintf(
                '%1$s%2$s%3$s',
                '<?xml version="1.0" encoding="UTF-8" ?>',
                $this->stylesheet,
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" />'
            )
        );

        foreach ($url_list as $url_item) {
            $url = $urlset->addChild('url');

            // Add each element as a child node to the <url> entry.
            foreach ($url_item as $name => $value) {
                if ('loc' === $name) {
                    $url->addChild($name, esc_url($value));
                } elseif (in_array($name, array('lastmod', 'changefreq', 'priority'), true)) {
                    $url->addChild($name, esc_xml($value));
                    /* [end] Unmodified from WP core */
                    //
                } elseif ('alternates' === $name) {
                    if (!empty($value)) {
                        foreach ($value as $lang => $link) {
                            $alternates = $url->addChild('xhtml:link');
                            $alternates->addAttribute('rel', 'alternate');
                            $alternates->addAttribute('hreflang', $lang);
                            $alternates->addAttribute('href', esc_url($link));
                        }
                    }
                    //
                    /* [start] Unmodified from WP core */
                } else {
                    _doing_it_wrong(
                        __METHOD__,
                        sprintf(
                            /* translators: %s: List of element names. */
                            __('Fields other than %s are not currently supported for sitemaps.'),
                            implode(',', array('loc', 'lastmod', 'changefreq', 'priority'))
                        ),
                        '5.5.0'
                    );
                }
            }
        }

        return $urlset->asXML();
    }
}
