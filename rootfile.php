<?php
require_once __DIR__ . '/vendor/autoload.php';
$GLOBALS['midcom_config_local'] = array();

// Check that the environment is a working one
midcom_connection::setup(__DIR__ . DIRECTORY_SEPARATOR);

$prefix = dirname($_SERVER['SCRIPT_NAME']) . '/';
if (strpos($_SERVER['REQUEST_URI'], $prefix) !== 0)
{
    $prefix = '/';
}
$prefix = '/';
define('OPENPSA2_PREFIX', $prefix);

header('Content-Type: text/html; charset=utf-8');

$GLOBALS['midcom_config_local']['theme'] = 'OpenPsa2';

if (file_exists(__DIR__ . '/config.inc.php'))
{
    include __DIR__ . '/config.inc.php';
}
else
{
    //TODO: Hook in an installation wizard here, once it is written
    include __DIR__ . '/config-default.inc.php';
}

if (! defined('MIDCOM_STATIC_URL'))
{
    define('MIDCOM_STATIC_URL', '/openpsa2-static');
}

if (file_exists(__DIR__ . '/themes/' . $GLOBALS['midcom_config_local']['theme'] . '/config.inc.php'))
{
    include __DIR__ . '/themes/' . $GLOBALS['midcom_config_local']['theme'] . '/config.inc.php';
}

// Start request processing
$midcom = midcom::get();
$midcom->codeinit();
$midcom->content();
$midcom->finish();
?>
