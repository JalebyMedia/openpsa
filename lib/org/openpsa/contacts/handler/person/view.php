<?php
/**
 * @package org.openpsa.contacts
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use midcom\datamanager\datamanager;
use midcom\datamanager\schemadb;

/**
 * Person display class
 *
 * @package org.openpsa.contacts
 */
class org_openpsa_contacts_handler_person_view extends midcom_baseclasses_components_handler
{
    use org_openpsa_contacts_handler;

    /**
     * The contact to display
     *
     * @var org_openpsa_contacts_person_dba
     */
    private $_contact;

    /**
     * The user object for the current person, if any
     *
     * @var midcom_core_user
     */
    private $_person_user;

    /**
     * The Datamanager of the contact
     *
     * @var datamanager
     */
    private $_datamanager;

    /**
     * Simple helper which references all important members to the request data listing
     * for usage within the style listing.
     */
    private function _prepare_request_data()
    {
        $this->_request_data['person'] = $this->_contact;
        $this->_request_data['datamanager'] = $this->_datamanager;
        $this->_request_data['person_user'] = $this->_person_user;
    }

    private function _load_datamanager()
    {
        $schemaname = $this->get_person_schema($this->_contact);
        $schemadb = schemadb::from_path($this->_config->get('schemadb_person'));
        $fields = $schemadb->get($schemaname)->get('fields');
        unset($fields['photo']);
        $schemadb->get($schemaname)->set('fields', $fields);
        $this->_datamanager = new datamanager($schemadb);
        $this->_datamanager->set_storage($this->_contact, $schemaname);
    }

    /**
     * Looks up a contact to display.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_view($handler_id, array $args, array &$data)
    {
        $this->_contact = new org_openpsa_contacts_person_dba($args[0]);

        $this->_load_datamanager();

        $data['person_rss_url'] = $this->_contact->get_parameter('net.nemein.rss', 'url');
        if ($data['person_rss_url']) {
            // We've autoprobed that this contact has a RSS feed available, link it
            midcom::get()->head->add_link_head([
                'rel'   => 'alternate',
                'type'  => 'application/rss+xml',
                'title' => sprintf($this->_l10n->get('rss feed of person %s'), $this->_contact->name),
                'href'  => $data['person_rss_url'],
            ]);
        }
        $this->_prepare_request_data();
        $this->add_stylesheet(MIDCOM_STATIC_URL . "/org.openpsa.contacts/contacts.css");
        midcom::get()->head->add_jsfile(MIDCOM_STATIC_URL . "/org.openpsa.helpers/editable.js");
        org_openpsa_widgets_ui::enable_ui_tab();
        $this->_populate_toolbar($handler_id);

        $this->bind_view_to_object($this->_contact, $this->_datamanager->get_schema()->get_name());

        $this->add_breadcrumb($this->router->generate('person_view', ['guid' => $this->_contact->guid]), $this->_contact->name);
        midcom::get()->head->set_pagetitle($this->_contact->name);
        $data['contact_view'] = $this->_datamanager->get_content_html();

        return $this->show('show-person');
    }

    /**
     * Populate the toolbar with the necessary items
     *
     * @param string $handler_id the ID of the current handler
     */
    private function _populate_toolbar($handler_id)
    {
        $workflow = $this->get_workflow('datamanager');
        $buttons = [];
        if ($this->_contact->can_do('midgard:update')) {
            $buttons[] = $workflow->get_button($this->router->generate('person_edit', ['guid' => $this->_contact->guid]), [
                MIDCOM_TOOLBAR_ACCESSKEY => 'e',
            ]);
        }

        $siteconfig = org_openpsa_core_siteconfig::get_instance();
        $invoices_url = $siteconfig->get_node_full_url('org.openpsa.invoices');
        $user_url = $siteconfig->get_node_full_url('org.openpsa.user');

        if (   $invoices_url
            && midcom::get()->auth->can_user_do('midgard:create', null, org_openpsa_invoices_invoice_dba::class)
            && $this->_contact->can_do('midgard:update')) {
            $buttons[] = $workflow->get_button($invoices_url . "billingdata/" . $this->_contact->guid . '/', [
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('edit billingdata'),
                MIDCOM_TOOLBAR_GLYPHICON => 'address-card'
            ]);
        }

        if (   $user_url
            && (   midcom_connection::get_user() == $this->_contact->id
                || midcom::get()->auth->can_user_do('org.openpsa.user:access', null, org_openpsa_user_interface::class))) {
            $buttons[] = [
                MIDCOM_TOOLBAR_URL => $user_url . "view/{$this->_contact->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_i18n->get_string('user management', 'org.openpsa.user'),
                MIDCOM_TOOLBAR_GLYPHICON => 'user-circle-o',
            ];
        }

        if ($this->_contact->can_do('midgard:delete')) {
            $workflow = $this->get_workflow('delete', ['object' => $this->_contact]);
            $buttons[] = $workflow->get_button($this->router->generate('person_delete', ['guid' => $this->_contact->guid]));
        }

        $mycontacts = new org_openpsa_contacts_mycontacts;

        if ($mycontacts->is_member($this->_contact->guid)) {
            // We're buddies, show remove button
            $buttons[] = [
                MIDCOM_TOOLBAR_URL => $this->router->generate('mycontacts_remove', ['guid' => $this->_contact->guid]),
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('remove from my contacts'),
                MIDCOM_TOOLBAR_GLYPHICON => 'ban',
            ];
        } else {
            // We're not buddies, show add button
            $buttons[] = [
                MIDCOM_TOOLBAR_URL => $this->router->generate('mycontacts_add', ['guid' => $this->_contact->guid]),
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('add to my contacts'),
                MIDCOM_TOOLBAR_GLYPHICON => 'plus',
            ];
        }
        $this->_view_toolbar->add_items($buttons);
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_group_memberships($handler_id, array $args, array &$data)
    {
        // Check if we get the person
        $data['person'] = new org_openpsa_contacts_person_dba($args[0]);

        $qb = org_openpsa_contacts_member_dba::new_query_builder();
        $qb->add_constraint('uid', '=', $data['person']->id);
        $qb->add_constraint('gid.orgOpenpsaObtype', '>', org_openpsa_contacts_group_dba::MYCONTACTS);
        $data['organizations'] = $qb->execute();

        $qb = org_openpsa_contacts_member_dba::new_query_builder();
        $qb->add_constraint('uid', '=', $data['person']->id);
        $qb->add_constraint('gid.orgOpenpsaObtype', '<', org_openpsa_contacts_group_dba::MYCONTACTS);
        $data['groups'] = $qb->execute();

        // This is most likely a dynamic_load
        midcom::get()->skip_page_style = true;
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_group_memberships($handler_id, array &$data)
    {
        if (   count($data['organizations']) == 0
            && count($data['groups']) == 0) {
            midcom_show_style('show-person-groups-empty');
        } else {
            $this->_show_memberships('organizations');
            $this->_show_memberships('groups');
        }
    }

    private function _show_memberships($identifier)
    {
        if (empty($this->_request_data[$identifier])) {
            return;
        }
        $this->_request_data['title'] = $this->_l10n->get($identifier);
        midcom_show_style('show-person-groups-header');
        foreach ($this->_request_data[$identifier] as $member) {
            try {
                $this->_request_data['group'] = org_openpsa_contacts_group_dba::get_cached($member->gid);
            } catch (midcom_error $e) {
                $e->log();
                continue;
            }
            $this->_request_data['member'] = $member;
            $this->_request_data['member_title'] = $member->extra;

            midcom_show_style('show-person-groups-item');
        }
        midcom_show_style('show-person-groups-footer');
    }
}
