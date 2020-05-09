<?php

namespace Bond\Fields;

/**
 *
 */
class File extends Field
{
    protected string $type = 'file';

    public function __construct(string $name, array $settings = [])
    {
        $defaults = [
            'mime_types' => 'pdf,zip',
        ];
        parent::__construct($name, array_merge($defaults, $settings));
    }
}
