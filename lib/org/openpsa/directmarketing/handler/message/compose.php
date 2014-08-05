<?php
/**
 * @package org.openpsa.directmarketing
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Discussion forum index
 *
 * @package org.openpsa.directmarketing
 */
class org_openpsa_directmarketing_handler_message_compose extends midcom_baseclasses_components_handler
{
    /**
     * The message which has been created
     *
     * @var org_openpsa_directmarketing_message
     */
    private $_message = null;

    /**
     * MidCOM helper Datamanager2 class
     *
     * @var midcom_helper_datamanager2_datamanager
     */
    private $_datamanager = false;

    /**
     * Internal helper, loads the datamanager for the current message. Any error triggers a 500.
     */
    private function _load_datamanager()
    {
        $schemadb = midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_message'));
        $this->_datamanager = new midcom_helper_datamanager2_datamanager($schemadb);
    }

    /**
     * Phase for composing a message
     *
     * @param string $handler_id    Name of the request handler
     * @param array $args           Variable arguments
     * @param array &$data          Public request data, passed by reference
     */
    public function _handler_compose($handler_id, array $args, array &$data)
    {
        midcom::get()->auth->request_sudo($this->_component);
        //Load message
        $this->_message = new org_openpsa_directmarketing_campaign_message_dba($args[0]);
        $data['campaign'] = new org_openpsa_directmarketing_campaign_dba($this->_message->campaign);

        $this->set_active_leaf('campaign_' . $data['campaign']->id);

        $this->_load_datamanager();
        $this->_datamanager->autoset_storage($this->_message);
        $data['message'] = $this->_message;
        $data['message_dm'] = $this->_datamanager;

        if ($handler_id === 'compose4person')
        {
            $data['person'] = new org_openpsa_contacts_person_dba($args[1]);
            $qb = org_openpsa_directmarketing_campaign_member_dba::new_query_builder();
            $qb->add_constraint('person', '=', $this->_request_data['person']->id);
            $memberships = $qb->execute();
            if (empty($memberships))
            {
                $data['member'] = new org_openpsa_directmarketing_campaign_member_dba();
                $data['member']->person = $data['person']->id;
                $data['member']->campaign = $this->_message->campaign;
            }
            else
            {
                $data['member'] = $memberships[0];
            }
        }

        $data['message_array'] = $this->_datamanager->get_content_raw();

        if (!array_key_exists('content', $data['message_array']))
        {
            throw new midcom_error('"content" not defined in schema');
        }
        //Substyle handling
        if (   !empty($data['message_array']['substyle'])
            && !preg_match('/^builtin:/', $data['message_array']['substyle']))
        {
            debug_add("Appending substyle {$data['message_array']['substyle']}");
            midcom::get()->style->append_substyle($data['message_array']['substyle']);
        }
        //This isn't necessary for dynamic-loading, but is nice for "preview".
        midcom::get()->skip_page_style = true;
        debug_add('message type: ' . $this->_message->orgOpenpsaObtype);
        switch ($this->_message->orgOpenpsaObtype)
        {
            case org_openpsa_directmarketing_campaign_message_dba::EMAIL_TEXT:
            case org_openpsa_directmarketing_campaign_message_dba::SMS:
                debug_add('Forcing content type: text/plain');
                midcom::get()->header('Content-type: text/plain');
                break;
            //TODO: Other content type overrides ?
        }
        midcom::get()->auth->drop_sudo();
    }

    /**
     * Compose the message and send it for post-formatting
     *
     * @param String $handler_id    Name of the request handler
     * @param array &$data          Public request data, passed by reference
     * @return String               Composed message
     */
    public function _show_compose($handler_id, array &$data)
    {
        if ($handler_id === 'compose4person')
        {
            ob_start();
            $this->_real_show_compose($handler_id, $data);
            $composed = ob_get_contents();
            ob_end_clean();
            $personalized = $data['member']->personalize_message($composed, $this->_message->orgOpenpsaObtype, $data['person']);
            echo $personalized;
            return;
        }
        $this->_real_show_compose($handler_id, $data);
    }

    private function _real_show_compose($handler_id, array &$data)
    {
        $prefix='';
        if (   !empty($data['message_array']['substyle'])
            && preg_match('/^builtin:(.*)/', $data['message_array']['substyle'], $matches_style))
        {
            $prefix = $matches_style[1].'-';
        }
        debug_add("Calling midcom_show_style(\"compose-{$prefix}message\")");
        midcom_show_style("compose-{$prefix}message");
    }
}
?>