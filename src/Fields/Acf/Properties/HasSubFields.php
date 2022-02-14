<?php

namespace Bond\Fields\Acf\Properties;

use Bond\Fields\Acf\BooleanField;
use Bond\Fields\Acf\CheckboxField;
use Bond\Fields\Acf\ColorField;
use Bond\Fields\Acf\DateField;
use Bond\Fields\Acf\DateTimeField;
use Bond\Fields\Acf\EmailField;
use Bond\Fields\Acf\Field;
use Bond\Fields\Acf\FileField;
use Bond\Fields\Acf\FlexibleContentField;
use Bond\Fields\Acf\GalleryField;
use Bond\Fields\Acf\GoogleMapField;
use Bond\Fields\Acf\GroupField;
use Bond\Fields\Acf\ImageField;
use Bond\Fields\Acf\MessageField;
use Bond\Fields\Acf\NumberField;
use Bond\Fields\Acf\OEmbedField;
use Bond\Fields\Acf\PasswordField;
use Bond\Fields\Acf\PostObjectField;
use Bond\Fields\Acf\RadioField;
use Bond\Fields\Acf\RangeField;
use Bond\Fields\Acf\RelationshipField;
use Bond\Fields\Acf\RepeaterField;
use Bond\Fields\Acf\SelectField;
use Bond\Fields\Acf\TabField;
use Bond\Fields\Acf\TaxonomyField;
use Bond\Fields\Acf\TextAreaField;
use Bond\Fields\Acf\TextField;
use Bond\Fields\Acf\TimeField;
use Bond\Fields\Acf\UrlField;
use Bond\Fields\Acf\WysiwygField;
use Bond\Utils\Str;
use Closure;

/**
 *
 * Adds all fields methods
 */
trait HasSubFields
{
    // must implement something like this
    // TODO test abstract method here
    // protected function addField(Field $field) : self
    // {
    //     $this->sub_fields[] = $field;
    //     return $this;
    // }


    // Test this more later
    // It works, but no IDE autofill
    // May just be better to allow only the flat var-based scope
    public function fields(Closure $closure): self
    {
        // can have autofill, if dev adds the type declaration
        $closure($this);

        // can not have autofill
        // except if the dev adds a annotation inside the closure
        // /** @var Repeater $this */
        // $closure->bindTo($this)();
        return $this;
    }
    // this method allow this syntax (better understanding of the nesting levels)
    // $flex->layout('test')
    //     ->label(t('Label'))
    //     ->fields(function () {
    //         $this->imageField('bkg_image')
    //             ->label(t('Background Image'))
    //             ->previewSize(MEDIUM);

    //         $this->repeaterField('images')
    //             ->layout('table')
    //             ->buttonLabel(t('Add Image'))
    //             ->fields(function () {

    //                 $this->imageField('image')
    //                     ->label(t('Image'))
    //                     ->previewSize(MEDIUM);
    //             });
    //     });

    private function _addField(Field $field, string $label = null)
    {
        if ($label !== null) {
            $field->label($label);
        }
        $this->addField($field);
        return $field;
    }

    public function field(string $name, string $label = null): Field
    {
        return $this->_addField(new Field($name), $label);
    }

    // Basic

    public function textField(string $name, string $label = null): TextField
    {
        return $this->_addField(new TextField($name), $label);
    }

    public function textAreaField($name, string $label = null): TextAreaField
    {
        return $this->_addField(new TextAreaField($name), $label);
    }

    public function numberField($name, string $label = null): NumberField
    {
        return $this->_addField(new NumberField($name), $label);
    }

    public function emailField($name, string $label = null): EmailField
    {
        return $this->_addField(new EmailField($name), $label);
    }

    public function urlField($name, string $label = null): UrlField
    {
        return $this->_addField(new UrlField($name), $label);
    }

    public function passwordField($name, string $label = null): PasswordField
    {
        return $this->_addField(new PasswordField($name), $label);
    }

    public function messageField($name, string $label = null): MessageField
    {
        return $this->_addField(new MessageField($name), $label);
    }

    public function tabField(string $label): TabField
    {
        $name = 'tab_' . Str::snake($label) . '_' . uniqid();

        return $this->_addField(new TabField($name), $label);
    }

    public function rangeField($name, string $label = null): RangeField
    {
        return $this->_addField(new RangeField($name), $label);
    }


    // Choice

    public function booleanField($name, string $label = null): BooleanField
    {
        return $this->_addField(new BooleanField($name), $label);
    }

    public function radioField($name, string $label = null): RadioField
    {
        return $this->_addField(new RadioField($name), $label);
    }

    public function checkboxField($name, string $label = null): CheckboxField
    {
        return $this->_addField(new CheckboxField($name), $label);
    }

    public function selectField($name, string $label = null): SelectField
    {
        return $this->_addField(new SelectField($name), $label);
    }



    // Content

    public function wysiwygField($name, string $label = null): WysiwygField
    {
        return $this->_addField(new WysiwygField($name), $label);
    }

    public function imageField($name, string $label = null): ImageField
    {
        return $this->_addField(new ImageField($name), $label);
    }

    public function fileField($name, string $label = null): FileField
    {
        return $this->_addField(new FileField($name), $label);
    }

    public function galleryField($name, string $label = null): GalleryField
    {
        return $this->_addField(new GalleryField($name), $label);
    }

    public function oEmbedField($name, string $label = null): OEmbedField
    {
        return $this->_addField(new OEmbedField($name), $label);
    }

    // Relational

    public function postObjectField($name, string $label = null): PostObjectField
    {
        return $this->_addField(new PostObjectField($name), $label);
    }

    public function relationshipField($name, string $label = null): RelationshipField
    {
        return $this->_addField(new RelationshipField($name), $label);
    }

    public function taxonomyField($name, string $label = null): TaxonomyField
    {
        return $this->_addField(new TaxonomyField($name), $label);
    }


    // Dynamic

    public function dateField($name, string $label = null): DateField
    {
        return $this->_addField(new DateField($name), $label);
    }

    public function dateTimeField($name, string $label = null): DateTimeField
    {
        return $this->_addField(new DateTimeField($name), $label);
    }

    public function timeField($name, string $label = null): TimeField
    {
        return $this->_addField(new TimeField($name), $label);
    }

    public function colorField($name, string $label = null): ColorField
    {
        return $this->_addField(new ColorField($name), $label);
    }

    public function googleMapField($name, string $label = null): GoogleMapField
    {
        return $this->_addField(new GoogleMapField($name), $label);
    }

    // Layout

    public function groupField($name, string $label = null): GroupField
    {
        return $this->_addField(new GroupField($name), $label);
    }

    public function repeaterField($name, string $label = null): RepeaterField
    {
        return $this->_addField(new RepeaterField($name), $label);
    }

    public function flexibleContentField($name, string $label = null): FlexibleContentField
    {
        return $this->_addField(new FlexibleContentField($name), $label);
    }
}
