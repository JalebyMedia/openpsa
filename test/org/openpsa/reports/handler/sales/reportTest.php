<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class org_openpsa_reports_handler_sales_reportTest extends openpsa_testcase
{
    public static function setUpBeforeClass()
    {
        self::create_user(true);
    }

    public function test_handler_generator_get()
    {
        midcom::get()->auth->request_sudo('org.openpsa.reports');

        $_REQUEST = array('org_openpsa_reports_query_data' => array('mimetype' => 'text/html'));

        $data = $this->run_handler('org.openpsa.reports', array('sales', 'get'));
        $this->assertEquals('sales_report_get', $data['handler_id']);

        midcom::get()->auth->drop_sudo();
    }

    public function test_handler_edit_report_guid()
    {
        midcom::get()->auth->request_sudo('org.openpsa.reports');

        $query = $this->create_object('org_openpsa_reports_query_dba');

        $data = $this->run_handler('org.openpsa.reports', array('sales', 'edit', $query->guid));
        $this->assertEquals('sales_edit_report_guid', $data['handler_id']);

        midcom::get()->auth->drop_sudo();
    }

    public function test_handler_report_guid_file()
    {
        midcom::get()->auth->request_sudo('org.openpsa.reports');

        $query = $this->create_object('org_openpsa_reports_query_dba');

        $data = $this->run_handler('org.openpsa.reports', array('sales', $query->guid, 'test.csv'));
        $this->assertEquals('sales_report_guid_file', $data['handler_id']);

        midcom::get()->auth->drop_sudo();
    }

    public function test_handler_report_guid()
    {
        midcom::get()->auth->request_sudo('org.openpsa.reports');

        $query = $this->create_object('org_openpsa_reports_query_dba');
        $timestamp = strftime('%Y_%m_%d', $query->metadata->created);

        $url = $this->run_relocate_handler('org.openpsa.reports', array('sales', $query->guid));

        $this->assertEquals('sales/' . $query->guid . '/' . $timestamp . '_unnamed.html', $url);

        midcom::get()->auth->drop_sudo();
    }

    public function test_handler_report()
    {
        midcom::get()->auth->request_sudo('org.openpsa.reports');

        $query = $this->create_object('org_openpsa_reports_query_dba');

        $data = $this->run_handler('org.openpsa.reports', array('sales'));
        $this->assertEquals('sales_report', $data['handler_id']);

        midcom::get()->auth->drop_sudo();
    }

}
?>