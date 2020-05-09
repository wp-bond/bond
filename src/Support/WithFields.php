<?php

namespace Bond\Support;

trait WithFields
{
    protected $has_loaded_fields = false;

    protected function loadFields()
    {
        if (!$this->has_loaded_fields) {
            $this->reloadFields();
        }
    }

    protected function reloadFields()
    {
        if (isset($this->ID)) {
            if (function_exists('\get_fields')) {
                $this->add(\get_fields($this->ID));
            }
            $this->has_loaded_fields = true;
        }
    }

    public function values(string $for = ''): Fluent
    {
        $this->loadFields();
        return new Fluent();
    }

    public function localize(): Fluent
    {
        $this->loadFields();
        return parent::localize();
    }

    public function __get($key)
    {
        $this->loadFields();
        return parent::__get($key);
    }

    public function __isset($key): bool
    {
        $this->loadFields();
        return isset($this->{$key});
    }

    public function all(): array
    {
        $this->loadFields();
        return parent::all();
    }

    public function unserialize($serialized)
    {
        parent::unserialize($serialized);
        $this->has_loaded_fields = true;
    }
}
