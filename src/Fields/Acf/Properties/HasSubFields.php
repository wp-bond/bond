<?php

namespace Bond\Fields\Acf\Properties;

use Bond\Fields\Acf\BooleanField;
use Bond\Fields\Acf\ColorPickerField;
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
use Bond\Fields\Acf\TaxonomyField;
use Bond\Fields\Acf\TextAreaField;
use Bond\Fields\Acf\TextField;
use Bond\Fields\Acf\UrlField;
use Bond\Fields\Acf\WysiwygField;
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

    private function _addField(Field $field)
    {
        $this->addField($field);
        return $field;
    }

    public function field(string $name): Field
    {
        return $this->_addField(new Field($name));
    }

    // Basic

    public function textField(string $name): TextField
    {
        return $this->_addField(new TextField($name));
    }

    public function textAreaField($name): TextAreaField
    {
        return $this->_addField(new TextAreaField($name));
    }

    public function numberField($name): NumberField
    {
        return $this->_addField(new NumberField($name));
    }

    public function emailField($name): EmailField
    {
        return $this->_addField(new EmailField($name));
    }

    public function urlField($name): UrlField
    {
        return $this->_addField(new UrlField($name));
    }

    public function passwordField($name): PasswordField
    {
        return $this->_addField(new PasswordField($name));
    }

    public function messageField($name): MessageField
    {
        return $this->_addField(new MessageField($name));
    }

    public function rangeField($name): RangeField
    {
        return $this->_addField(new RangeField($name));
    }


    // Choice

    public function booleanField($name): BooleanField
    {
        return $this->_addField(new BooleanField($name));
    }

    public function radioField($name): RadioField
    {
        return $this->_addField(new RadioField($name));
    }

    public function selectField($name): SelectField
    {
        return $this->_addField(new SelectField($name));
    }



    // Content

    public function wysiwygField($name): WysiwygField
    {
        return $this->_addField(new WysiwygField($name));
    }

    public function imageField($name): ImageField
    {
        return $this->_addField(new ImageField($name));
    }

    public function fileField($name): FileField
    {
        return $this->_addField(new FileField($name));
    }

    public function galleryField($name): GalleryField
    {
        return $this->_addField(new GalleryField($name));
    }

    public function oEmbedField($name): OEmbedField
    {
        return $this->_addField(new OEmbedField($name));
    }

    // Relational

    public function postObjectField($name): PostObjectField
    {
        return $this->_addField(new PostObjectField($name));
    }

    public function relationshipField($name): RelationshipField
    {
        return $this->_addField(new RelationshipField($name));
    }

    public function taxonomyField($name): TaxonomyField
    {
        return $this->_addField(new TaxonomyField($name));
    }


    // Dynamic

    public function dateField($name): DateField
    {
        return $this->_addField(new DateField($name));
    }

    public function dateTimeField($name): DateTimeField
    {
        return $this->_addField(new DateTimeField($name));
    }

    public function colorPickerField($name): ColorPickerField
    {
        return $this->_addField(new ColorPickerField($name));
    }

    public function googleMapField($name): GoogleMapField
    {
        return $this->_addField(new GoogleMapField($name));
    }

    // Layout

    public function groupField($name): GroupField
    {
        return $this->_addField(new GroupField($name));
    }

    public function repeaterField($name): RepeaterField
    {
        return $this->_addField(new RepeaterField($name));
    }

    public function flexibleContentField($name): FlexibleContentField
    {
        return $this->_addField(new FlexibleContentField($name));
    }
}
