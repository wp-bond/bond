<?php

namespace Bond\Fields\Acf;

/**
 *
 */
class GoogleMapField extends Field
{
    protected string $type = 'google_map';

    public function __construct(string $name, array $settings = [])
    {
        $defaults = [
            'center_lat' => ini_get('date.default_latitude') ?: '',
            'center_lng' => ini_get('date.default_longitude') ?: '',
        ];
        parent::__construct($name, array_merge($defaults, $settings));
    }
}
