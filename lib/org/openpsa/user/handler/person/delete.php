<?php
/**
 * @package org.openpsa.user
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * Delete person class for user management
 *
 * @package org.openpsa.user
 */
class org_openpsa_user_handler_person_delete extends midcom_baseclasses_components_handler
{
    /**
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_delete($handler_id, array $args, array &$data)
    {
        $person = new midcom_db_person($args[0]);
        if ($person->id != midcom_connection::get_user())
        {
            midcom::get()->auth->require_user_do('org.openpsa.user:manage', null, 'org_openpsa_user_interface');
        }

        $workflow = new org_openpsa_core_workflow_delete($person);
        if ($workflow->run())
        {
            $indexer = midcom::get()->indexer;
            $indexer->delete($person->guid);
            return new midcom_response_relocate('');
        }
        return new midcom_response_relocate('view/' . $person->guid . '/');
    }
}
