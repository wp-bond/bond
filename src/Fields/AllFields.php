<?php

namespace Bond\Fields;

/**
 * Adds all fields methods
 */
trait AllFields
{
    // must implement something like this
    // TODO test abstract method here
    // protected function addField(Field $field) : self
    // {
    //     $this->sub_fields[] = $field;
    //     return $this;
    // }

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

    public function textField(string $name): Text
    {
        return $this->_addField(new Text($name));
    }

    public function textAreaField($name): TextArea
    {
        return $this->_addField(new TextArea($name));
    }

    public function numberField($name): Number
    {
        return $this->_addField(new Number($name));
    }

    public function emailField($name): Email
    {
        return $this->_addField(new Email($name));
    }

    public function urlField($name): Url
    {
        return $this->_addField(new Url($name));
    }

    public function passwordField($name): Password
    {
        return $this->_addField(new Password($name));
    }

    public function messageField($name): Message
    {
        return $this->_addField(new Message($name));
    }

    // Choice

    public function booleanField($name): Boolean
    {
        return $this->_addField(new Boolean($name));
    }

    // Content

    public function wysiwygField($name): Wysiwyg
    {
        return $this->_addField(new Wysiwyg($name));
    }

    public function imageField($name): Image
    {
        return $this->_addField(new Image($name));
    }

    public function fileField($name): File
    {
        return $this->_addField(new File($name));
    }

    public function galleryField($name): Gallery
    {
        return $this->_addField(new Gallery($name));
    }

    public function oEmbedField($name): OEmbed
    {
        return $this->_addField(new OEmbed($name));
    }

    // Relational

    public function postObjectField($name): PostObject
    {
        return $this->_addField(new PostObject($name));
    }

    public function relationshipField($name): Relationship
    {
        return $this->_addField(new Relationship($name));
    }

    public function taxonomyField($name): Taxonomy
    {
        return $this->_addField(new Taxonomy($name));
    }


    // Dynamic

    public function dateTimeField($name): DateTime
    {
        return $this->_addField(new DateTime($name));
    }

    public function googleMapField($name): GoogleMap
    {
        return $this->_addField(new GoogleMap($name));
    }

    // Layout

    public function groupField($name): Group
    {
        return $this->_addField(new Group($name));
    }

    public function repeaterField($name): Repeater
    {
        return $this->_addField(new Repeater($name));
    }

    public function flexibleContentField($name): FlexibleContent
    {
        return $this->_addField(new FlexibleContent($name));
    }
}
