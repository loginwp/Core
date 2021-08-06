<?php

use LoginWP\Core\Helpers;
use LoginWP\Core\Redirections\Redirections;

// Typically this function is used in templates, similarly to the wp_register function
// It returns a link to the administration panel or the one that was custom defined
// If no user is logged in, it returns the "Register" link
// You can specify tags to go around the returned link (or wrap it with no tags); by default this is a list item
// You can also specify whether to print out the link or just return it
function rul_register($before = '<li>', $after = '</li>', $give_echo = true)
{
    global $current_user;

    if ( ! is_user_logged_in()) {
        if (get_option('users_can_register')) {
            $link = $before . '<a href="' . wp_registration_url() . '">' . __('Register', 'peters-login-redirect') . '</a>' . $after;
        } else {
            $link = '';
        }
    } else {
        $link = $before . '<a href="' . Helpers::login_redirect_logic_callback('', '', $current_user) . '">' . __('Site Admin', 'peters-login-redirect') . '</a>' . $after;
    }

    if ($give_echo) {
        echo $link;
    } else {
        return $link;
    }
}

function loginwpPOST_var($key, $default = false, $empty = false, $bucket = false)
{
    $bucket = ! $bucket ? $_POST : $bucket;

    if ($empty) {
        return ! empty($bucket[$key]) ? $bucket[$key] : $default;
    }

    return isset($bucket[$key]) ? $bucket[$key] : $default;
}

function loginwpGET_var($key, $default = false, $empty = false)
{
    $bucket = $_GET;

    if ($empty) {
        return ! empty($bucket[$key]) ? $bucket[$key] : $default;
    }

    return isset($bucket[$key]) ? $bucket[$key] : $default;
}

function loginwp_var($bucket, $key, $default = false, $empty = false)
{
    if ($empty) {
        return ! empty($bucket[$key]) ? $bucket[$key] : $default;
    }

    return isset($bucket[$key]) ? $bucket[$key] : $default;
}

function loginwp_var_obj($bucket, $key, $default = false, $empty = false)
{
    if ($empty) {
        return ! empty($bucket->$key) ? $bucket->$key : $default;
    }

    return isset($bucket->$key) ? $bucket->$key : $default;
}

function wplogin_redirect_control_function()
{
    $redirect_url = Redirections::login_redirect_callback(admin_url(), '', wp_get_current_user());
    wp_redirect($redirect_url);
    exit;
}
