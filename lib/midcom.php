<?php
/**
 * @package midcom
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * @package midcom
 */
class midcom
{
    /**
     * MidCOM version
     *
     * @var string
     */
    private static $_version = '9.0.0-rc.1+git';

    /**
     * Main application singleton
     *
     * @var midcom_application
     */
    private static $_application;

    /**
     * This is the interface to MidCOMs Object Services.
     *
     * Each service is indexed by its string-name (for example "i18n"
     * for all i18n stuff).
     *
     * @var Array
     */
    private static $_services = array();

    /**
     * Mapping of service names to classes implementing the service
     */
    private static $_service_classes = array
    (
        'auth' => 'midcom_services_auth',
        'componentloader' => 'midcom_helper__componentloader',
        'cache' => 'midcom_services_cache',
        'config' => 'midcom_config',
        'dbclassloader' => 'midcom_services_dbclassloader',
        'dbfactory' => 'midcom_helper__dbfactory',
        'dispatcher' => '\\midcom\\events\\dispatcher',
        'debug' => 'midcom_debug',
        'head' => 'midcom_helper_head',
        'i18n' => 'midcom_services_i18n',
        'indexer' => 'midcom_services_indexer',
        'metadata' => 'midcom_services_metadata',
        'permalinks' => 'midcom_services_permalinks',
        'rcs' => 'midcom_services_rcs',
        'serviceloader' => 'midcom_helper_serviceloader',
        'session' => 'midcom_services__sessioning',
        'style' => 'midcom_helper__styleloader',
        'tmp' => 'midcom_services_tmp',
        'toolbars' => 'midcom_services_toolbars',
        'uimessages' => 'midcom_services_uimessages',
    );

    public static function init()
    {
        // Instantiate the MidCOM main class
        self::$_application = new midcom_application();
        self::get('debug')->log("Start of MidCOM run" . (isset($_SERVER['REQUEST_URI']) ? ": {$_SERVER['REQUEST_URI']}" : ''));
        self::$_services['auth'] = new midcom_services_auth;

        /* Load and start up the cache system, this might already end the request
         * on a content cache hit. Note that the cache check hit depends on the i18n and auth code.
         */
        self::$_services['cache'] = new midcom_services_cache;

        if (self::$_services['config']->get('midcom_compat_ragnaroek'))
        {
            require_once __DIR__ . '/compat/bootstrap.php';
        }

        self::$_application->initialize();

        if (   self::$_services['config']->get('midcom_compat_ragnaroek')
            && file_exists(MIDCOM_CONFIG_FILE_AFTER))
        {
            include MIDCOM_CONFIG_FILE_AFTER;
        }
    }

    /**
     * Get midcom_application instance
     *
     * Services can also be loaded this way by passing their name as an argument,
     * but this feature will likely be removed at some point
     *
     * @param string $name The service name as listed in the _service_classes array or null to get midcom_application
     * @return midcom_application The midcom application instance
     */
    public static function get($name = null)
    {
        if (!self::$_application)
        {
            self::init();
        }

        if (null === $name)
        {
            return self::$_application;
        }

        if (isset(self::$_services[$name]))
        {
            return self::$_services[$name];
        }

        if (isset(self::$_service_classes[$name]))
        {
            $service_class = self::$_service_classes[$name];
            self::$_services[$name] = new $service_class;
            return self::$_services[$name];
        }

        throw new midcom_error("Requested service '$name' is not available.");
    }

    public static function get_version()
    {
        return self::$_version;
    }
}
?>
