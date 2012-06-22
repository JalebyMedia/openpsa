<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

if (!defined('OPENPSA_TEST_ROOT'))
{
    define('OPENPSA_TEST_ROOT', dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . DIRECTORY_SEPARATOR);
    require_once(OPENPSA_TEST_ROOT . 'rootfile.php');
}

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class org_openpsa_reports_handler_invoices_reportTest extends openpsa_testcase
{
    public static function setUpBeforeClass()
    {
        self::create_user(true);
        self::create_class_object('org_openpsa_invoices_invoice_dba');
    }

    public function test_handler_generator_get()
    {
        midcom::get('auth')->request_sudo('org.openpsa.reports');

        $_REQUEST = array('org_openpsa_reports_query_data' => array('mimetype' => 'text/html'));

        $data = $this->run_handler('org.openpsa.reports', array('invoices', 'get'));
        $this->assertEquals('invoices_report_get', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }

    public function test_handler_edit_report_guid()
    {
        midcom::get('auth')->request_sudo('org.openpsa.reports');

        $query = $this->create_object('org_openpsa_reports_query_dba');

        $data = $this->run_handler('org.openpsa.reports', array('invoices', 'edit', $query->guid));
        $this->assertEquals('invoices_edit_report_guid', $data['handler_id']);

        $this->show_handler($data);
        midcom::get('auth')->drop_sudo();
    }

    public function test_handler_report_guid_file()
    {
        midcom::get('auth')->request_sudo('org.openpsa.reports');

        $query = $this->create_object('org_openpsa_reports_query_dba');
        $statuses =  array
        (
            'open',
            'unsent',
            'scheduled'
        );
        $query->set_parameter('midcom.helper.datamanager2', 'invoice_status', serialize($statuses));
        $query->set_parameter('midcom.helper.datamanager2', 'date_field', 'date');
        $query->set_parameter('midcom.helper.datamanager2', 'resource', 'all');

        $data = $this->run_handler('org.openpsa.reports', array('invoices', $query->guid, 'test.csv'));
        $this->assertEquals('invoices_report_guid_file', $data['handler_id']);

        $this->show_handler($data);
        midcom::get('auth')->drop_sudo();
    }

    public function test_handler_report_guid()
    {
        midcom::get('auth')->request_sudo('org.openpsa.reports');

        $query = $this->create_object('org_openpsa_reports_query_dba');
        $timestamp = strftime('%Y_%m_%d', $query->metadata->created);

        $url = $this->run_relocate_handler('org.openpsa.reports', array('invoices', $query->guid));

        $this->assertEquals('invoices/' . $query->guid . '/' . $timestamp . '_unnamed.html', $url);

        midcom::get('auth')->drop_sudo();
    }

    public function test_handler_report()
    {
        midcom::get('auth')->request_sudo('org.openpsa.reports');

        $data = $this->run_handler('org.openpsa.reports', array('invoices'));
        $this->assertEquals('invoices_report', $data['handler_id']);

        $this->show_handler($data);
        midcom::get('auth')->drop_sudo();
    }
}
?>