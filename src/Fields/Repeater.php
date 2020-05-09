<?php

namespace Bond\Fields;

/**
 *
 */
class Repeater extends Field
{
    use AllFields;

    protected string $type = 'repeater';
    protected array $sub_fields = [];

    protected function addField(Field $field): self
    {
        $this->sub_fields[] = $field;
        return $this;
    }

    // TODO provide a method like this:
    // helps to not break the chain, but also visually organizes code

    // $group->repeaterField('example')
    //     ->subFields(function ($repeater) {
    //         $repeater->textField('sample');
    //         $repeater->textField('anothersample');
    //     });
}
