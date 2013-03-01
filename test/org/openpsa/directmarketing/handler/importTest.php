<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

require_once OPENPSA_TEST_ROOT . 'org/openpsa/directmarketing/__helper/campaign.php';

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class org_openpsa_directmarketing_handler_importTest extends openpsa_testcase
{
    protected static $_person;

    public static function setUpBeforeClass()
    {
        self::$_person = self::create_user(true);
    }

    public function testHandler_index()
    {
        $helper = new openpsa_test_campaign_helper($this);
        $campaign = $helper->get_campaign();

        midcom::get('auth')->request_sudo('org.openpsa.directmarketing');

        $data = $this->run_handler('org.openpsa.directmarketing', array('campaign', 'import', $campaign->guid));
        $this->assertEquals('import_main', $data['handler_id']);
        $this->show_handler($data);

        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_simpleemails()
    {
        $helper = new openpsa_test_campaign_helper($this);
        $campaign = $helper->get_campaign();

        midcom::get('auth')->request_sudo('org.openpsa.directmarketing');

        $data = $this->run_handler('org.openpsa.directmarketing', array('campaign', 'import', 'simpleemails', $campaign->guid));
        $this->assertEquals('import_simpleemails', $data['handler_id']);
        $this->show_handler($data);

        $_POST = array
        (
            'org_openpsa_directmarketing_import_separator' => 'N',
            'org_openpsa_directmarketing_import_textarea' => __METHOD__ . '.' . time() . '@' . __CLASS__ . '.org',
        );
        $_FILES = array
        (
            'org_openpsa_directmarketing_import_upload' => array
            (
                'tmp_name' => null
            )
        );
        $data = $this->run_handler('org.openpsa.directmarketing', array('campaign', 'import', 'simpleemails', $campaign->guid));
        $this->assertArrayHasKey('import_status', $data);
        $this->assertEquals(1, $data['import_status']['subscribed_new']);

        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_csv_select()
    {
        $helper = new openpsa_test_campaign_helper($this);
        $campaign = $helper->get_campaign();

        midcom::get('auth')->request_sudo('org.openpsa.directmarketing');

        $data = $this->run_handler('org.openpsa.directmarketing', array('campaign', 'import', 'csv', $campaign->guid));
        $this->assertEquals('import_csv_file_select', $data['handler_id']);
        $this->show_handler($data);
        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_vcards()
    {
        $helper = new openpsa_test_campaign_helper($this);
        $campaign = $helper->get_campaign();

        midcom::get('auth')->request_sudo('org.openpsa.directmarketing');

        $data = $this->run_handler('org.openpsa.directmarketing', array('campaign', 'import', 'vcards', $campaign->guid));
        $this->assertEquals('import_vcards', $data['handler_id']);
        $this->show_handler($data);
        midcom::get('auth')->drop_sudo();
    }
}
?>