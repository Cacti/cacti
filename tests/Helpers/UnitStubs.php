<?php

if (!function_exists('cacti_sizeof')) {
    function cacti_sizeof($array) {
        return ($array === false || !is_array($array)) ? 0 : sizeof($array);
    }
}

if (!function_exists('cacti_log')) {
    function cacti_log($message, $print = false, $tag = 'GENERAL', $level = 1) {
        // Silently ignore
    }
}

if (!function_exists('__')) {
    function __($text, ...$args) {
        return vsprintf($text, $args);
    }
}

if (!function_exists('__esc')) {
    function __esc($text, ...$args) {
        return vsprintf($text, $args);
    }
}

if (!function_exists('set_request_var')) {
    function set_request_var($name, $val) {
        $_REQUEST[$name] = $val;
    }
}

if (!function_exists('srv')) {
    function srv($name, $val) {
        $_REQUEST[$name] = $val;
    }
}

if (!function_exists('cacti_strtolower')) {
    function cacti_strtolower($string) {
        return mb_strtolower($string);
    }
}

if (!function_exists('read_config_option')) {
    function read_config_option($name) {
        return false;
    }
}

if (!function_exists('read_user_setting')) {
    function read_user_setting($name) {
        return '08:00';
    }
}
