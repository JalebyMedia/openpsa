<?php
/**
 * The class containing the configuration options for RCS.
 *
 * @author tarjei huse
 * @package midcom.services.rcs
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * The config class is used to generate the RCS configuration.
 *
 * @see midcom_services_rcs for an overview of the options
 * @package midcom.services.rcs
 */
class midcom_services_rcs_config
{
    /**
     * The array of configuration options
     *
     * @var array
     */
    public $config = null;

    /**
     * Constructor
     */
    public function __construct($config_array)
    {
        $this->config = $config_array;
    }

    /**
     * Factory function for the handler object.
     *
     * @return midcom_services_rcs_backend
     */
    function get_handler(&$object)
    {
        $class = $this->_get_handler_class();
        return new $class($object, $this);
    }

    /**
     *
     * This method should return an array() of config options.
     */
    function _get_config()
    {
        return $this->config;
    }

    /**
     * Returns the root of the directory containg the RCS files.
     */
    function get_rcs_root()
    {
        return $this->config['midcom_services_rcs_root'];
    }

    /**
     * If the RCS service is enabled
     * (set by midcom_services_rcs_use)
     *
     * @return boolean true if it is enabled
     */
    function use_rcs()
    {
        if (isset($this->config['midcom_services_rcs_enable']))
        {
            return $this->config['midcom_services_rcs_enable'];
        }

        return false;
    }

    /**
     * Returns the prefix for the rcs utilities.
     */
    function get_bin_prefix()
    {
        return $this->config['midcom_services_rcs_bin_dir'];
    }

    /**
     * Loads the backend file needed and returns the class.
     *
     * @return string of the backend to start
     */
    function _get_handler_class()
    {
        if (!empty($this->config['midcom_services_rcs_enable']))
        {
            $this->_test_rcs_config();
            return 'midcom_services_rcs_backend_rcs';
        }
        else
        {
            return 'midcom_services_rcs_backend_null';
        }
    }

    /**
     * Checks if the basic rcs service is usable.
     */
    private function _test_rcs_config()
    {
        if (!isset($this->config['midcom_services_rcs_root']))
        {
            throw new midcom_error("midcom_services_rcs_root not found in configuration.");
        }

        if (!is_writable($this->config['midcom_services_rcs_root']))
        {
            throw new midcom_error("The root RCS directory {$this->config['midcom_services_rcs_root']} is not writable!");
        }

        if (!isset($this->config['midcom_services_rcs_bin_dir']))
        {
            throw new midcom_error("midcom_services_rcs_bin_dir not found in configuration. This must be defined before RCS will work.");
        }

        if (!is_executable($this->config['midcom_services_rcs_bin_dir'] . "/ci"))
        {
            throw new midcom_error("Cannot execute {$this->config['midcom_services_rcs_bin_dir']}/ci.");
        }
    }
 }
?>