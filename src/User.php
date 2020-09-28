<?php

namespace Bond;

use Bond\Support\Fluent;
use Bond\Utils\Cast;
use WP_User;

class User extends Fluent
{
    public int $ID;
    public string $role;

    // set properties that should not be added here
    protected array $exclude = [
        'user_pass',
        'user_activation_key',
        'filter',
    ];

    public function __construct($values = null)
    {
        $this->init($values);
    }

    protected function init($values)
    {
        if (empty($values)) {
            return;
        }

        // if is numeric or string we'll fetch the WP_User and load fields
        // if is WP_User we'll just add it and load fields
        if (
            is_numeric($values)
            || is_string($values)
            || $values instanceof WP_User
        ) {
            $user = Cast::wpUser($values);
            if ($user) {
                // unwrap the data
                $this->add($user->data);
                $user->data = null;

                // add the rest
                $this->add($user);

                // auto set the role
                if (!isset($this->role) && !empty($user->roles)) {
                    $this->role = $user->roles[0];
                }

                // load all fields
                $this->loadFields();
            }
            return;
        }

        // otherwise (object or array) are honored as the full value
        // and added WITHOUT loading fields
        $this->add($values);
    }

    public function loadFields()
    {
        $this->add($this->getFields());
    }

    public function getFields(): ?array
    {
        if (isset($this->ID) && app()->hasAcf()) {
            return \get_fields('user_' . $this->ID) ?: null;
        }
        return null;
    }

    // public function link(string $language_code = null): string
    // {
    //  TODO send to search with the slug or something
    //     return Link::forUsers($this, $language_code);
    // }
}
