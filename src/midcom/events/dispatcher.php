<?php
/**
 * @package midcom.events
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midcom\events;

use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * midcom event dispatcher
 *
 * @package midcom.events
 */
class dispatcher extends EventDispatcher
{
    /**
     * This array contains all registered MidCOM operation watches. They are indexed by
     * operation and map to components / libraries which have registered to classes.
     * Values consist of an array whose first element is the component and subsequent
     * elements are the types involved (so a single count means all objects).
     *
     * @var array
     */
    private $_watches = array
    (
        \MIDCOM_OPERATION_DBA_CREATE => dbaevent::CREATE,
        \MIDCOM_OPERATION_DBA_UPDATE => dbaevent::UPDATE,
        \MIDCOM_OPERATION_DBA_DELETE => dbaevent::DELETE,
        \MIDCOM_OPERATION_DBA_IMPORT => dbaevent::IMPORT,
    );

    public function trigger_watch($operation_id, $object)
    {
        $event_name = $this->_watches[$operation_id];
        $event = new dbaevent($object);
        $this->dispatch($event_name, $event);
    }

    public function add_watches(array $watches, $component)
    {
        foreach ($watches as $watch)
        {
            // Check for every operation we know and register the watches.
            // We make shortcuts for less typing.
            $operations = $watch['operations'];
            $watch_info = $watch['classes'];
            if ($watch_info === null)
            {
                $watch_info = Array();
            }

            // Add the component name into the watch information, it is
            // required for later processing of the watch.
            array_unshift($watch_info, $component);

            foreach ($this->_watches as $operation_id => $event_name)
            {
                // Check whether the operations flag list from the component
                // contains the operation_id we're checking a watch for.
                if ($operations & $operation_id)
                {
                    $listener = new dbalistener($component, $watch['classes']);
                    $this->addListener($event_name, array($listener, 'handle_event'));
                }
            }
        }
    }
}
