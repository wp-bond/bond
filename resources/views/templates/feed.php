<?php

use Bond\Utils\Cast;

header('Content-Type: ' . feed_content_type('rss-http') . '; charset=UTF-8', true);

// echo to avoid get parsed as short tags
echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;

?><rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:sy="http://purl.org/rss/1.0/modules/syndication/">

    <channel>
        <title><?= $this->title ?></title>
        <atom:link href="<?= $this->url ?>" rel="self" type="application/rss+xml" />
        <link><?= app()->url() ?></link>
        <description><?= $this->description ?></description>
        <lastBuildDate><?= $this->last_build_date ?></lastBuildDate>
        <language><?= $this->language ?></language>
        <sy:updatePeriod><?= $this->update_period ?></sy:updatePeriod>
        <sy:updateFrequency><?= $this->update_frequency ?></sy:updateFrequency>
        <copyright><?= $this->copyright ?></copyright>

        <?php if ($this->image) : ?>
            <image>
                <url><?= $this->image ?></url>
                <title><?= $this->title ?></title>
                <link><?= app()->url() ?></link>
            </image>
        <?php
        endif;

        global $post;

        while (have_posts()) {
            the_post();

            // get our post class
            $p = Cast::post($post);

            // try first a post type specific
            $this->partial('feed-item-' . $p->post_type, $p)
                ?: $this->partial('feed-item', $p);

            // templating will look in this order:
            // partials/feed-item-{post_type}-{feed_key} (at theme view folder)
            // partials/feed-item-{post_type}-{feed_key} (at bond source)
            // partials/feed-item-{post_type} (at theme view folder)
            // partials/feed-item-{post_type} (at bond source)
            // partials/feed-item-{feed_key} (at theme view folder)
            // partials/feed-item-{feed_key} (at bond source)
            // partials/feed-item (at theme view folder)
            // partials/feed-item (at bond source)
        }
        ?>
    </channel>
</rss>
