<?php

namespace Bond\Fields\Acf;

/**
 *
 */
class MessageField extends Field
{
    protected string $type = 'message';

    public function message(
        string $message,
        string $written_language = null
    ): self {
        $this->message = tx($message, 'fields', null, $written_language);
        return $this;
    }

    public function lineBreakNone(): self
    {
        $this->new_lines = '';
        return $this;
    }

    public function lineBreakBr(): self
    {
        $this->new_lines = 'br';
        return $this;
    }

    public function lineBreakParagraph(): self
    {
        $this->new_lines = 'wpautop';
        return $this;
    }

    public function escapeHtml(bool $active = true): self
    {
        $this->esc_html = $active;
        return $this;
    }
}
