<?php

namespace Bond\Fields;

/**
 *
 */
class Message extends Field
{
    protected string $type = 'message';

    public function message(string $message): self
    {
        $this->message = $message;
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
