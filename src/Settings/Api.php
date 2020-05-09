<?php

namespace Bond\Settings;

use WP_Error;

class Api
{
    // more helpers soon

    public static function disable()
    {
        static::disableHeader();
        static::disableDefaultRoutes();
        // TODO will be more strict, really disable entirelly
    }

    public static function disableHeader()
    {
        // HTTP header
        \remove_action('template_redirect', 'rest_output_link_header', 11);

        // HTML <head>
        \remove_action('wp_head', 'rest_output_link_wp_head', 10);
        \remove_action('xmlrpc_rsd_apis', 'rest_output_rsd');
    }


    public static function disableDefaultRoutes()
    {
        \remove_action('rest_api_init', 'create_initial_rest_routes', 99);
    }

    public static function onlyLoggedIn()
    {
        \add_filter('rest_authentication_errors', function ($access) {
            if (!\is_user_logged_in()) {
                return new WP_Error(
                    'rest_login_required',
                    t('Not allowed'),
                    ['status' => 401]
                );
            }
            return $access;
        });
    }
}
