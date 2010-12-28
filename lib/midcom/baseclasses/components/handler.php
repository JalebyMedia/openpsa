<?php
/**
 * @package midcom.baseclasses
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Execution handler subclass, to be used with the request switch
 * in midcom_baseclasses_components_request.
 *
 * Use the various event handlers to customize startup.
 *
 * The basic idea is that you have separate instances of this type for the various
 * operations in your main viewer class. This avoids cluttering up the viewer class
 * and gives you better maintainability due to smaller code files.
 *
 * Under normal operation within the same component you don't need to think about any
 * specialties, the member variables are just references to the main request class
 * (also known as "viewer class").
 *
 * Noteworthy is the ability to export handlers for usage in other components in
 * both libraries and full components. To make the exported handler work correctly,
 * you need to set $this->_component to the corresponding value of the <i>exporting</i>
 * component. In this case, the startup code will take the main l10n instance, the
 * component data storage and the configuration <i>from the exporting component.</i>
 * The configuration in this case is merged from the global defaults (constructed
 * during component/library startup) and the configuration parameters set on the topic
 * <i>where it is invoked.</i>
 *
 * Note, that the export "mode" is only invoked <i>if and only if</i> the component of
 * the handler is <i>different</i> of the component of the main request class.
 *
 * @package midcom.baseclasses
 */
abstract class midcom_baseclasses_components_handler extends midcom_baseclasses_components_base
{
    /**#@+
     * Request state variable, set during startup. There should be no need to change it
     * in most cases.
     *
     * @access protected
     */

    /**
     * The topic for which we are handling a request.
     *
     * @var midcom_db_topic
     */
    var $_topic = null;

    /**
     * Request specific data storage area. Registered in the component context
     * as ''.
     *
     * @var Array
     */
    var $_request_data = array();

    /**
     * A reference to the request class that has invoked this handler instance.
     *
     * @var midcom_baseclasses_components_request
     */
    var $_master = null;

    /**
     * The node toolbar for the current request context. Not available during the can_handle
     * phase.
     *
     * @var midcom_helper_toolbar
     * @see midcom_services_toolbars
     */
    var $_node_toolbar = null;

    /**
     * The view toolbar for the current request context. Not available during the can_handle
     * phase.
     *
     * @var midcom_helper_toolbar
     * @see midcom_services_toolbars
     */
    var $_view_toolbar = null;

    /**#@-*/

    /**
     * Holds breadcrumb entries the handler wants to add
     *
     * @var array
     */
    private $_breadcrumbs = array();

    /**
     * Main constructor does not do much yet, it shouldn't be overridden though,
     * use the _on_initilize event handler instead.
     */
    public function __construct()
    {
    }


    /**
     * Initializes the request handler class, called by the component interface after
     * instantiation. Required to allow safe $this references during startup.
     *
     * Be aware that it is possible that a handler can come from a different component
     * (or library) then the master class. Take this into account when getting the
     * component data storage, configuration and l10n instances. Configuration is merged
     * during runtime based on the system defaults and all parameters attached to the
     * topic <i>we're currently operating on.</i>
     *
     * @param midcom_baseclasses_components_request &$master A reference to the request class
     *     handling the request.
     */
    function initialize(&$master)
    {
        $this->_master =& $master;

        $this->_request_data =& $master->_request_data;
        $this->_topic =& $master->_topic;

        // Load component specific stuff, special treatment if the handler has
        // a component different then the master handler set.
        if (   $this->_component
            && $this->_component != $master->_component)
        {
            $this->_config->store_from_object($this->_topic, $this->_component);
        }
        else
        {
            $this->_component = $master->_component;
        }
        $this->_on_initialize();
    }


    /**#@+
     * Event Handler callback.
     */

    /**
     * Initialization event handler, called at the end of the initialization process.
     * Use this for all initialization work you need, as the component state is already
     * populated when this event handler is called.
     */
    public function _on_initialize()
    {
    }

    /**#@-*/

    /**
     * Registers a new breadcrumb entry
     *
     * @param string $url The URL
     * @param string $title The text to display
     */
    public function add_breadcrumb($url, $title)
    {
        $this->_breadcrumbs[] = array
        (
            MIDCOM_NAV_URL => $url,
            MIDCOM_NAV_NAME => $title,
        );
    }

    /**
     * Adds the registered breadcrumb entries to context_data
     */
    public function populate_breadcrumb_line()
    {
        if (empty($this->_breadcrumbs))
        {
            return;
        }
        $_MIDCOM->set_custom_context_data('midcom.helper.nav.breadcrumb', $this->_breadcrumbs);
    }

    /**
     * Helper function for quick access to diverse datamanager controllers.
     *
     * For this to work, the handler has to implement the respective DM2 interface
     *
     * @todo Maybe we should do a class_implements check here
     * @param string $type The controller type
     * @param midcom_core_dbaobject $object The object, if any
     */
    public function get_controller($type, $object = null)
    {
        switch ($type)
        {
            case 'simple':
                return midcom_helper_datamanager2_handler::get_simple_controller($this, $object);
            case 'nullstorage':
                return midcom_helper_datamanager2_handler::get_nullstorage_controller($this);
            case 'create':
                return midcom_helper_datamanager2_handler::get_create_controller($this);
            default:
                throw new midcom_error("Unsupported controller type: {$type}");
        }
    }

    /**
     * Default helper function for DM2 schema-related operations
     *
     * return string The default DM2 schema name
     */
    public function get_schema_name()
    {
        return 'default';
    }

    /**
     * Default helper function for DM2 schema-related operations
     *
     * return string The default DM2 schema name
     */
    public function get_schema_defaults()
    {
        return array();
    }
}
?>