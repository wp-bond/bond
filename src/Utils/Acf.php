<?php

namespace Bond\Utils;

/**
 * Provides some helpers for ACF.
 */
class Acf
{
    public static function getFields($post_id): array
    {
        $control_list = static::getControlFields($post_id);
        return static::unwrapControlField($control_list);
    }

    public static function unwrapControlField($field)
    {
        if (is_array($field)) {

            $values = [];
            foreach ($field as $k => $v) {

                if (is_array($v)) {

                    $values[$k] = static::unwrapControlField(
                        isset($v['type']) && isset($v['value']) ? $v['value'] : $v
                    );
                } else {
                    $values[$k] = $v;
                }
            }
            return $values;
        }

        return $field;
    }

    public static function getControlFields($post_id): array
    {
        // get all post fields without formatting
        $acf = \get_field_objects($post_id, false);
        if (empty($acf)) {
            return [];
        }

        // assemble the control field list
        $fields = [];
        foreach ($acf as $name => $values) {
            $fields[$name] = [
                'type' => $values['type'],
                'value' => static::acfFieldValue($values),
            ];
        }

        return $fields;
    }


    public static function acfFieldValue($values)
    {
        if (in_array($values['type'], [
            'flexible_content',
            'repeater',
        ])) {

            // put together all sub fields
            if ($values['type'] === 'repeater') {
                $values['layouts'] = [
                    ['sub_fields' => $values['sub_fields']]
                ];
            }
            $lookup = [];
            foreach ($values['layouts'] as $layout) {
                foreach ($layout['sub_fields'] as $sub) {
                    $lookup[$sub['key']] = [
                        'name' => $sub['name'],
                        'type' => $sub['type'],
                        'layouts' => $sub['layouts'] ?? null,
                        'sub_fields' => $sub['sub_fields'] ?? null,
                    ];
                }
            }

            // format all sub values
            $value = [];
            if (!empty($values['value'])) {
                foreach ($values['value'] as $layout) {
                    $layout_value = [];

                    foreach ($layout as $k => $v) {

                        if ($k === 'acf_fc_layout') {
                            $layout_value[$k] = [
                                'type' => '',
                                'value' => $v,
                            ];
                        } else {
                            $field = $lookup[$k];
                            $field['value'] = $v;

                            $layout_value[$field['name']] = [
                                'type' => $field['type'],
                                'value' => static::acfFieldValue($field),
                            ];
                        }
                    }

                    $value[] = $layout_value;
                }
            }
            return $value;
        }

        if ($values['type'] === 'group') {

            // put together all sub fields
            $lookup = [];
            foreach ($values['sub_fields'] as $sub) {
                $lookup[$sub['key']] = [
                    'name' => $sub['name'],
                    'type' => $sub['type'],
                    'layouts' => $sub['layouts'] ?? null,
                    'sub_fields' => $sub['sub_fields'] ?? null,
                ];
            }

            // format all sub values
            $value = [];
            if (!empty($values['value'])) {
                foreach ($values['value'] as  $k => $v) {

                    $field = $lookup[$k];
                    $field['value'] = $v;

                    $value[$field['name']] = [
                        'type' => $field['type'],
                        'value' => static::acfFieldValue($field),
                    ];
                }
            }

            return $value;
        }

        switch ($values['type']) {
            case 'gallery':
            case 'relationship':
                return array_map('intval', empty($values['value']) ? [] : (array)$values['value']);

            case 'true_false':
                return (bool) $values['value'];

            case 'number':
                return (float) $values['value'];

            case 'image':
            case 'file':
                return (int) $values['value'];

            case 'post_object':
                return is_array($values['value'])
                    ? array_map('intval', $values['value'])
                    : (int) $values['value'];

                // TODO more?

            default;
                break;
        }


        // for all other field types, just return the value
        return $values['value'];
    }
}
