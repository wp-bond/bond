<?php

namespace Bond\Fields\Acf;

class BooleanField extends Field
{
    protected string $type = 'true_false';
    public bool $ui = true;

    public function labelTrue(
        string $label,
        string $written_language = null
    ): self {
        $this->ui_on_text = tx($label, 'fields', null, $written_language);
        return $this;
    }

    public function labelFalse(
        string $label,
        string $written_language = null
    ): self {
        $this->ui_off_text = tx($label, 'fields', null, $written_language);
        return $this;
    }

    public function message(
        string $message,
        string $written_language = null
    ): self {
        $this->message = tx($message, 'fields', null, $written_language);
        return $this;
    }
}
