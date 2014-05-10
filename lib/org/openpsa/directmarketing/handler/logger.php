<?php
/**
 * @package org.openpsa.directmarketing
 * @author Nemein Oy http://www.nemein.com/
 * @copyright Nemein Oy http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * org.openpsa.directmarketing campaign handler and viewer class.
 * @package org.openpsa.directmarketing
 */
class org_openpsa_directmarketing_handler_logger extends midcom_baseclasses_components_handler
{
    /**
     * Logs a bounce from bounce_detector.php for POSTed token, marks the send receipt
     * and the campaign member as bounced.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_bounce($handler_id, array $args, array &$data)
    {
        if (empty($_POST['token']))
        {
            throw new midcom_error('Token not present in POST or empty');
        }
        $this->_request_data['update_status'] = array('receipts' => array(), 'members' => array());

        midcom::get('auth')->request_sudo('org.openpsa.directmarketing');
        debug_add("Looking for token '{$_POST['token']}' in sent receipts");
        $ret = $this->_qb_token_receipts($_POST['token']);
        debug_print_r("_qb_token_receipts({$_POST['token']}) returned", $ret);
        if (empty($ret))
        {
            midcom::get('auth')->drop_sudo();
            throw new midcom_error_notfound("No receipts with token '{$_POST['token']}' found");
        }
        //While in theory we should have only one token lets use foreach just to be sure
        foreach ($ret as $receipt)
        {
            //Mark receipt as bounced
            debug_add("Found receipt #{$receipt->id}, marking bounced");
            $receipt->bounced = time();
            $this->_request_data['update_status']['receipts'][$receipt->guid] = $receipt->update();

            //Mark member(s) as bounced (first get campaign trough message)
            $message = org_openpsa_directmarketing_campaign_message_dba::get_cached($receipt->message);
            $campaign = org_openpsa_directmarketing_campaign_dba::get_cached($message->campaign);

            debug_add("Receipt belongs to message '{$message->title}' (#{$message->id}) in campaign '{$campaign->title}' (#{$campaign->id})");

            $qb2 = org_openpsa_directmarketing_campaign_member_dba::new_query_builder();
            $qb2->add_constraint('orgOpenpsaObtype', '=', org_openpsa_directmarketing_campaign_member_dba::NORMAL);
            //PONDER: or should be just mark the person bounced in ALL campaigns while we're at it ?
            $qb2->add_constraint('campaign', '=', $campaign->id);
            $qb2->add_constraint('person', '=', $receipt->person);
            $ret2 = $qb2->execute();
            if (empty($ret2))
            {
                continue;
            }
            foreach ($ret2 as $member)
            {
                debug_add("Found member #{$member->id}, marking bounced");
                $member->orgOpenpsaObtype = org_openpsa_directmarketing_campaign_member_dba::BOUNCED;
                $this->_request_data['update_status']['members'][$member->guid] = $member->update();
            }
        }

        midcom::get('auth')->drop_sudo();
        midcom::get()->skip_page_style = true;
        midcom::get('cache')->content->content_type('text/plain');
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_bounce($handler_id, array &$data)
    {
        echo "OK\n";
        //PONDER: check  $this->_request_data['update_status'] and display something else in case all is not ok ?
    }

    /**
     * QB search for message receipts with given token and type
     *
     * @param string $token token string
     * @param int $type receipt type, defaults to org_openpsa_directmarketing_campaign_messagereceipt_dba::SENT
     * @return array QB->execute results
     */
    private function _qb_token_receipts($token, $type = org_openpsa_directmarketing_campaign_messagereceipt_dba::SENT)
    {
        $qb = org_openpsa_directmarketing_campaign_messagereceipt_dba::new_query_builder();
        $qb->add_constraint('token', '=', $token);
        $qb->add_constraint('orgOpenpsaObtype', '=', $type);
        return $qb->execute();
    }

    /**
     * Logs a link click from link_detector.php for POSTed token, binds to person
     * and creates received and read receipts as well
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_link($handler_id, array $args, array &$data)
    {
        if (empty($_POST['token']))
        {
            throw new midcom_error('Token not present in POST or empty');
        }
        if (empty($_POST['link']))
        {
            throw new midcom_error('Link not present in POST or empty');
        }

        midcom::get('auth')->request_sudo('org.openpsa.directmarketing');
        debug_add("Looking for token '{$_POST['token']}' in sent receipts");
        $ret = $this->_qb_token_receipts($_POST['token']);
        debug_print_r("_qb_token_receipts({$_POST['token']}) returned", $ret);
        if (empty($ret))
        {
            midcom::get('auth')->drop_sudo();
            throw new midcom_error_notfound("No receipts with token '{$_POST['token']}' found");
        }
        //While in theory we should have only one token lets use foreach just to be sure
        foreach ($ret as $receipt)
        {
            $this->_create_link_receipt($receipt, $_POST['token'], $_POST['link']);
        }

        midcom::get('auth')->drop_sudo();
        midcom::get()->skip_page_style = true;
        midcom::get('cache')->content->content_type('text/plain');
    }

    private function _create_link_receipt($receipt, $token, $target)
    {
        if (!array_key_exists('create_status', $this->_request_data))
        {
            $this->_request_data['create_status'] = array('receipts' => array(), 'links' => array());
        }

        //Store the click in database
        $link = new org_openpsa_directmarketing_link_log_dba();
        $link->person = $receipt->person;
        $link->message = $receipt->message;
        $link->target = $target;
        $link->token = $token;
        $this->_request_data['create_status']['links'][$target] = $link->create();

        //Create received and read receipts
        $read_receipt = new org_openpsa_directmarketing_campaign_messagereceipt_dba();
        $read_receipt->person = $receipt->person;
        $read_receipt->message = $receipt->message;
        $read_receipt->token = $token;
        $read_receipt->orgOpenpsaObtype = org_openpsa_directmarketing_campaign_messagereceipt_dba::RECEIVED;
        $this->_request_data['create_status']['receipts'][$token] = $read_receipt->create();
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_link($handler_id, array &$data)
    {
        echo "OK\n";
        //PONDER: check $this->_request_data['create_status'] and display something else in case all is not ok ?
    }

    /**
     * Duplicates link_detector.php functionality in part (to avoid extra apache configurations)
     * and handles the logging mentioned above as well.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_redirect($handler_id, array $args, array &$data)
    {
        if (empty($args[0]))
        {
            throw new midcom_error('Token empty');
        }
        $this->_request_data['token'] = $args[0];

        if (   count($args) == 2
            && !empty($args[1]))
        {
            //Due to the way browsers handle the URLs this form only works for root pages
            $this->_request_data['target'] = $args[1];
        }
        else if (!empty($_GET['link']))
        {
            $this->_request_data['target'] = $_GET['link'];
        }
        else
        {
            throw new midcom_error('Target not present in address or GET, or is empty');
        }

        //TODO: valid target domains check

        //If we have a dummy token don't bother with looking for it, just go on.
        if ($this->_request_data['token'] === 'dummy')
        {
            return new midcom_response_relocate($this->_request_data['target']);
        }

        midcom::get('auth')->request_sudo('org.openpsa.directmarketing');
        debug_add("Looking for token '{$this->_request_data['token']}' in sent receipts");
        $ret = $this->_qb_token_receipts($this->_request_data['token']);
        if (empty($ret))
        {
            midcom::get('auth')->drop_sudo();
            throw new midcom_error_notfound("No receipts with token '{$this->_request_data['token']}' found");
        }

        //While in theory we should have only one token lets use foreach just to be sure
        foreach ($ret as $receipt)
        {
            $this->_create_link_receipt($receipt, $this->_request_data['token'], $this->_request_data['target']);
        }

        midcom::get('auth')->drop_sudo();
        midcom::get()->skip_page_style = true;
        return new midcom_response_relocate($this->_request_data['target']);
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_redirect($handler_id, array &$data)
    {
        //TODO: make an element to display in case our relocate fails (with link to the intended target...)
    }
}
?>