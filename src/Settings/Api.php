<?php

namespace Bond\Settings;

use WP_Error;

class Api
{
    // more helpers soon

    public static function disable()
    {
        // disable headers
        static::disableHeader();

        // disable API entirelly
        \remove_action('init', 'rest_api_init');
    }

    public static function changePrefix(string $name)
    {
        \add_filter('rest_url_prefix', function () use ($name) {
            return $name;
        });
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
        // do not disable if is on admin
        if (app()->isAdmin()) {
            return;
        }

        // default routes
        \remove_action('rest_api_init', 'create_initial_rest_routes', 99);
    }


    public static function disableRootRoute()
    {
        // does not disable if is on admin
        if (app()->isAdmin()) {
            return;
        }

        // root endpoint
        \add_filter('rest_endpoints', function ($endpoints) {
            unset($endpoints['/']);
            return $endpoints;
        });
    }


    public static function disableOembed()
    {
        // oembeds
        \remove_action('rest_api_init', 'wp_oembed_register_route');
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
