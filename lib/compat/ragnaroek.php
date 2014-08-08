<?php
/**
 * MidCOM Ragnaroek compatibility functions
 *
 * @package midcom.compat
 */
function _midcom_header($string, $replace = true, $http_response_code = null)
{
    midcom_compat_environment::get()->header($string, $replace, $http_response_code);
}

/**
 * MidCOM Ragnaroek compatibility functions
 *
 * @package midcom.compat
 */
function _midcom_stop_request($message = '')
{
    midcom_compat_environment::get()->stop_request($message);
}

/**
 * MidCOM Ragnaroek compatibility functions
 *
 * @package midcom.compat
 */
function _midcom_headers_sent()
{
    return midcom_compat_environment::get()->headers_sent();
}

/**
 * MidCOM Ragnaroek compatibility functions
 *
 * @package midcom.compat
 */
function _midcom_setcookie($name, $value = '', $expire = 0, $path = '/', $domain = null, $secure = false, $httponly = false)
{
    return midcom_compat_environment::get()->setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
}
