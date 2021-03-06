<?php
/**
 * @package midcom.admin.folder
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Metadata editor.
 *
 * @package midcom.admin.folder
 */
class midcom_admin_folder_handler_metadata extends midcom_baseclasses_components_handler
{
    /**
     * Object requested for metadata editing
     *
     * @var midcom_core_dbaobject
     */
    private $object;

    /**
     * Handler for folder metadata. Checks for updating permissions, initializes
     * the metadata and the content topic itself. Handles also the sent form.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_metadata($handler_id, array $args, array &$data)
    {
        $this->object = midcom::get()->dbfactory->get_object_by_guid($args[0]);
        $this->object->require_do('midgard:update');

        if (is_a($this->object, midcom_db_topic::class)) {
            $this->object->require_do('midcom.admin.folder:topic_management');
        }

        $object_label = midcom_helper_reflector::get($this->object)->get_object_label($this->object);
        midcom::get()->head->set_pagetitle(sprintf($this->_l10n->get('edit metadata of %s'), $object_label));

        $workflow = $this->get_workflow('datamanager', [
            'controller' => $this->object->metadata->get_datamanager()->get_controller(),
            'save_callback' => [$this, 'save_callback']
        ]);
        return $workflow->run();
    }

    public function save_callback()
    {
        midcom::get()->cache->invalidate($this->object->guid);
    }
}
