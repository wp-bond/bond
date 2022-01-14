<item>
    <title><?= $this->title() ?></title>
    <link><?= app()->url() . $this->link() ?></link>
    <guid><?= app()->url() . $this->link() ?></guid>
    <pubDate><?= $this->dateGmt()->toRssString() ?></pubDate>
    <?php

    // categories
    foreach ($this->terms() as $term) {
        echo '<category><![CDATA[' . $term->name() . ']]></category>';
    }

    // let's put together a basic content, an image and excerpt
    use Bond\Utils\Image;
    use Bond\Utils\Str;

    $content = '';

    if ($image_id = $this->imageId()) {
        $content .= '<p><a href="' . app()->url() . $this->link() . '">';
        $content .= Image::imageTag($image_id, 'medium_large');
        $content .= '</a></p>';
    }
    if ($excerpt = $this->content()) {
        $result .= '<p>' . Str::clean($excerpt, 90) . '</p>';
    }
    $content .= '<p><a href="' . app()->url() . $this->link() . '">[' . tx('Link', 'rss', null, 'en') . ']</a></p>';

    if (!empty($content)) {
        echo '<content:encoded><![CDATA[' . $content . ']]></content:encoded>';
        echo '<description>' . Str::clean($content, 40) . '</description>';
    }

    // example on how to add creator
    // echo '<dc:creator><![CDATA[' . $this->author()?->name() . ']]></dc:creator>';

    // I decided not to show the author by default as on many sites the WP editors are not actually the article author.
    // if needed just create a 'partials/feed-item.php' template on your theme and adjust according to your needs.
    ?>
</item>
