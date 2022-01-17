<?php

namespace Bond\Services;

interface ServiceInterface
{
    public function config(?bool $enabled = null);
    public function enable();
    public function disable();
}
