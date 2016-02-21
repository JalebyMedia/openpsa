<?php
/**
 * @package org.openpsa.user
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * Edit person class for user management
 *
 * @package org.openpsa.user
 */
class org_openpsa_user_handler_person_edit extends midcom_baseclasses_components_handler
implements midcom_helper_datamanager2_interfaces_edit
{
    /**
     * The person we're working on
     *
     * @var midcom_db_person
     */
    private $_person;

    /**
     * Loads and prepares the schema database.
     *
     * The operations are done on all available schemas within the DB.
     */
    public function load_schemadb()
    {
        return midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_person'));
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_edit($handler_id, array $args, array &$data)
    {
        $this->_person = new org_openpsa_contacts_person_dba($args[0]);

        if ($this->_person->id != midcom_connection::get_user())
        {
            midcom::get()->auth->require_user_do('org.openpsa.user:manage', null, 'org_openpsa_user_interface');
        }

        $data['controller'] = $this->get_controller('simple', $this->_person);
        switch ($data['controller']->process_form())
        {
            case 'save':
                midcom::get()->uimessages->add($this->_l10n->get('org.openpsa.user'), sprintf($this->_l10n->get('person %s saved'), $this->_person->name));
                // Fall-through

            case 'cancel':
                return new midcom_response_relocate('view/' . $this->_person->guid . '/');
        }

        $this->add_breadcrumb('', sprintf($this->_l10n_midcom->get('edit %s'), $this->_person->get_label()));

        $this->bind_view_to_object($this->_person);
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_edit($handler_id, array &$data)
    {
        midcom_show_style('show-person-edit');
    }
}
