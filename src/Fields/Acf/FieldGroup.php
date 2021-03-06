<?php

namespace Bond\Fields\Acf;

use Bond\Fields\Acf\Properties\HasSubFields;
use Bond\Utils\Str;
use Bond\Settings\Language;

// TODO add multilanguageTabs to Group and Flex layout too
// Maybe this FieldGroup class could extend the Field itself, just like GroupField?

// MAYBE emit exception if the key has hyphens (just had this case when registering a flex layout) or disable this feature

class FieldGroup
{
    use HasSubFields;

    private $settings;
    private $apply_multilanguage_tabs = false;
    private static array $used_locations = [];


    public function __construct(string $key)
    {
        // stop if ACF is not loaded
        // useful if the theme is activated before the ACF plugin is
        if (!function_exists('\acf_add_local_field_group')) {
            return;
        }

        // sanitize
        $key = Str::azLower($key);

        // group key must be unique across the entire app
        static $i = 0;
        $group_key = 'group_' . $key . ++$i;

        // set
        $this->settings = [
            'key' => $group_key,
            'title' => '&nbsp;', // required, just change later
            'fields' => [],
        ];

        // auto register
        // right after the post and terms registrations
        \add_action('init', function () use ($key) {

            // copy settings
            $settings = $this->settings;

            // get fields array
            $fields = [];
            foreach ($settings['fields'] as $field) {

                if ($field->isMultilanguage()) {
                    $fields = array_merge(
                        $fields,
                        $field->toArrayMultilanguage()
                    );
                } else {
                    $fields[] = $field->toArray();
                }
            }

            // set field's keys
            $fields = self::setFieldsKeys(
                $fields,
                '',
                $key
            );;

            // i18n tabs
            if ($this->apply_multilanguage_tabs) {
                $fields = self::applyMultilanguageTabs($fields);
            }

            // dd($fields);

            // register
            $settings['fields'] = $fields;
            \acf_add_local_field_group($settings);
        }, 11);
    }

    protected function addField(Field $field): self
    {
        $this->settings['fields'][] = $field;
        return $this;
    }

    public function title(
        string $title,
        string $written_language = null
    ): self {

        $this->settings['title'] = tx($title, 'fields', null, $written_language);

        if (!$this->settings['title']) {
            $this->settings['title'] = '&nbsp;';
        }
        return $this;
    }

    public function location($location): self
    {
        // auto increment order
        // very handy so field groups appear in the same order as they are created in PHP
        if (!isset($this->settings['menu_order'])) {
            $hash = md5(json_encode($location));

            if (isset(static::$used_locations[$hash])) {
                $order = ++static::$used_locations[$hash];
            } else {
                static::$used_locations[$hash] = 1;
                $order = 1;
            }
            $this->order($order);
        }

        // set location
        $this->settings['location'] = self::formatLocation($location);

        return $this;
    }

    public function order(int $index): self
    {
        $this->settings['menu_order'] = (int) $index;
        return $this;
    }

    public function positionSide(): self
    {
        $this->settings['position'] = 'side';
        return $this;
    }

    public function positionAfterTitle(): self
    {
        $this->settings['position'] = 'acf_after_title';
        return $this;
    }

    public function seamless(): self
    {
        $this->settings['style'] = 'seamless';
        return $this;
    }

    public function labelLeft(): self
    {
        $this->settings['label_placement'] = 'left';
        return $this;
    }

    public function instructionsAfterField(): self
    {
        $this->settings['instruction_placement'] = 'field';
        return $this;
    }

    public function screenHideAll(array $except = []): self
    {
        $all = [
            'permalink',
            'the_content',
            'excerpt',
            'discussion',
            'comments',
            'revisions',
            'slug',
            'author',
            'format',
            'page_attributes',
            'featured_image',
            'categories',
            'tags',
            'send-trackbacks',
        ];
        $all = array_diff($all, $except);

        $this->screenHide($all);
        return $this;
    }

    public function screenHide(array $elements): self
    {
        $this->settings['hide_on_screen'] = $elements;
        return $this;
    }


    // TODO IDEA create a multilanguageGrouping
    // that groups all fields of each language together, very useful
    public function multilanguageTabs(): self
    {
        $this->apply_multilanguage_tabs = true;
        return $this;
    }


    private static function formatLocation($locations): array
    {
        $result = [];

        foreach ((array) $locations as $location => $options) {

            // handle indexed array
            if (intval($location) === $location) {
                $location = $options;
            }

            // honor pre-formated locations
            if (is_array($location)) {
                $result[] = $location;
                continue;
            }

            switch ($location) {

                case ATTACHMENT:
                    $result[] = [
                        [
                            'param' => ATTACHMENT,
                            'operator' => '==',
                            'value' => 'all',
                        ],
                    ];
                    break;

                case 'options':
                case 'options_page':
                    $result[] = [
                        [
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => 'acf-options-' . $options,
                        ],
                    ];
                    break;

                case 'tax':
                case 'taxonomy':
                    foreach ((array)$options as $taxonomy) {
                        $result[] = [
                            [
                                'param' => 'taxonomy',
                                'operator' => '==',
                                'value' => $taxonomy,
                            ],
                        ];
                    }
                    break;

                default:
                    $result[] = [
                        [
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => $location,
                        ],
                    ];
                    break;
            }
        }

        return $result;
    }


    private static function setFieldsKeys(
        array $fields,
        $prefix = '',
        $suffix = ''
    ): array {

        $result = [];
        foreach ($fields as $field) {
            $result[] = self::setFieldKey($field, $prefix, $suffix);
        }
        return $result;
    }

    private static function setFieldKey(
        array $field,
        $prefix = '',
        $suffix = ''
    ): array {
        // if there is field key, honor it
        // otherwise auto-set from the name
        if (!empty($field['key'])) {
            $name = $field['key'];

            if (strpos($name, 'field_') === 0) {
                $name = substr_replace($name, '', 0, 6);
            }
        } else {
            $name = $field['name'];
        }

        // store prefix in the name to recurse to sub_fields
        if ($prefix) {
            $name = $prefix . '_' . $name;
        }

        // set key
        $field['key'] = 'field_' . $name . ($suffix ? '_' . $suffix : '');

        // recurse to children
        if (!empty($field['sub_fields'])) {
            $field['sub_fields'] = self::setFieldsKeys(
                $field['sub_fields'],
                $name,
                $suffix
            );
        }
        if (!empty($field['layouts'])) {
            // dd($name, $suffix);
            $field['layouts'] = self::setFieldsKeys(
                $field['layouts'],
                $name,
                $suffix
            );
        }

        // go into conditional as well
        // if there is suffix
        if ($suffix && !empty($field['conditional_logic'])) {

            $conditional = [];
            foreach ($field['conditional_logic'] as $ors) {
                $_ands = [];
                foreach ($ors as $and) {
                    $and['field'] .= '_' . $suffix;

                    if (strpos($and['field'], 'field_') !== 0) {
                        $and['field'] = 'field_' . ($prefix ? $prefix . '_' : '') . $and['field'];
                    }

                    $_ands[] = $and;
                }
                $conditional[] = $_ands;
            }
            $field['conditional_logic'] = $conditional;
        }

        return $field;
    }

    private static function applyMultilanguageTabs(array $fields)
    {
        $result = [];
        $tabs = [];

        foreach ($fields as $field) {

            foreach (Language::all() as $code => $values) {

                if (!empty($field['i18n_skip_tabs'])) {
                    continue;
                }

                $suffix = Language::fieldsSuffix($code);

                if (str_ends_with($field['name'], $suffix)) {

                    if (empty($tabs[$suffix])) {
                        $tabs[$suffix] = [];
                        $tabs[$suffix][] = self::createTabField($values['name']);
                    }

                    $tabs[$suffix][] = $field;
                    continue 2;
                }
            }

            $result[] = $field;
        }

        foreach ($tabs as $fields) {
            $result = array_merge($result, $fields);
        }

        return $result;
    }

    private static function createTabField($label, array $options = [])
    {
        $key_suffix = Str::snake($label) . '_' . uniqid();

        $field = [
            'key' => 'field_tab_' . $key_suffix,
            'label' => $label,
            'name' => '',
            'type' => 'tab',
            'placement' => !empty($options['placement']) ? $options['placement'] : 'top',
            'value' => false,
            'endpoint' => !empty($options['endpoint']),
        ];

        return $field;
    }
}
