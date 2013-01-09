<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

if (!defined('OPENPSA_TEST_ROOT'))
{
    define('OPENPSA_TEST_ROOT', dirname(dirname(dirname(dirname(dirname(__FILE__))))) . DIRECTORY_SEPARATOR);
    require_once OPENPSA_TEST_ROOT . 'rootfile.php';
}

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class org_openpsa_invoices_handler_listTest extends openpsa_testcase
{
    protected static $_person;

    public static function setUpBeforeClass()
    {
        self::$_person = self::create_user(true);
    }

    public function testHandler_dashboard()
    {
        midcom::get('auth')->request_sudo('org.openpsa.invoices');

        $data = $this->run_handler('org.openpsa.invoices', array());
        $this->assertEquals('dashboard', $data['handler_id']);

        $this->show_handler($data);
        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_json()
    {
        midcom::get('auth')->request_sudo('org.openpsa.invoices');

        $data = $this->run_handler('org.openpsa.invoices', array('list', 'json', 'all'));
        $this->assertEquals('list_json_type', $data['handler_id']);

        $this->show_handler($data);
        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_customer()
    {
        midcom::get('auth')->request_sudo('org.openpsa.invoices');

        $data = $this->run_handler('org.openpsa.invoices', array('list', 'customer', 'all', self::$_person->guid));
        $this->assertEquals('list_customer_all', $data['handler_id']);

        $this->show_handler($data);
        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_deliverable()
    {
        midcom::get('auth')->request_sudo('org.openpsa.invoices');

        $salesproject = $this->create_object('org_openpsa_sales_salesproject_dba');
        $deliverable = $this->create_object('org_openpsa_sales_salesproject_deliverable_dba', array('salesproject' => $salesproject->id));

        $invoice  = $this->create_object('org_openpsa_invoices_invoice_dba');
        $attributes = array
        (
            'invoice' => $invoice->id,
            'deliverable' => $deliverable->id
        );
        $item  = $this->create_object('org_openpsa_invoices_invoice_item_dba', $attributes);

        $data = $this->run_handler('org.openpsa.invoices', array('list', 'deliverable', $deliverable->guid));
        $this->assertEquals('list_deliverable_all', $data['handler_id']);

        $this->show_handler($data);
        midcom::get('auth')->drop_sudo();
    }
}
?>