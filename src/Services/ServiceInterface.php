<?php

namespace Bond\Services;

interface ServiceInterface
{
    public function isEnabled(): bool;
    public function enable();
    public function disable();
}
