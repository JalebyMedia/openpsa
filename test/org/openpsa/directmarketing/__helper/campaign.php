<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * OpenPSA testcase helper class
 *
 * @package openpsa.test
 */
class openpsa_test_campaign_helper
{
    private $_testcase;

    private $_campaign;
    private $_member;
    private $_message;

    public function __construct(openpsa_testcase $testcase)
    {
        $this->_testcase = $testcase;
    }

    public function get_campaign()
    {
        if (!$this->_campaign)
        {
            $topic = openpsa_testcase::get_component_node('org.openpsa.directmarketing');

            $this->_campaign = $this->_testcase->create_object('org_openpsa_directmarketing_campaign_dba', array('node' => $topic->id));
        }

        return $this->_campaign;
    }

    public function get_member(midcom_db_person $person)
    {
        if (!$this->_member)
        {
            $campaign = $this->get_campaign();
            $parameters = array
            (
                'campaign' => $campaign->id,
                'person' => $person->id
            );
            $this->_member = $this->_testcase->create_object('org_openpsa_directmarketing_campaign_member_dba', $parameters);
        }
        return $this->_member;
    }

    public function get_log(org_openpsa_directmarketing_campaign_message_dba $message, midcom_db_person $person)
    {
        $parameters = array
        (
            'message' => $message->id,
            'person' => $person->id,
            'timestamp' => time(),
            'token' => __CLASS__ . __FUNCTION__,
            'target' => 'http://openpsa2.org'
        );
        return $this->_testcase->create_object('org_openpsa_directmarketing_link_log_dba', $parameters);
    }

    public function get_receipt(org_openpsa_directmarketing_campaign_message_dba $message, midcom_db_person $person)
    {
        $parameters = array
        (
            'message' => $message->id,
            'person' => $person->id,
            'timestamp' => time(),
            'token' => __CLASS__ . __FUNCTION__,
            'orgOpenpsaObtype' => org_openpsa_directmarketing_campaign_messagereceipt_dba::SENT
        );
        return $this->_testcase->create_object('org_openpsa_directmarketing_campaign_messagereceipt_dba', $parameters);
    }

    public function get_message()
    {
        if (!$this->_message)
        {
            $campaign = $this->get_campaign();
            $parameters = array
            (
                'campaign' => $campaign->id,
            );
            $this->_message = $this->_testcase->create_object('org_openpsa_directmarketing_campaign_message_dba', $parameters);
        }
        return $this->_message;
    }
}
?>