<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

if (!defined('OPENPSA_TEST_ROOT'))
{
    define('OPENPSA_TEST_ROOT', dirname(dirname(dirname(dirname(__FILE__)))) . DIRECTORY_SEPARATOR);
    require_once(OPENPSA_TEST_ROOT . 'rootfile.php');
}

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class org_openpsa_invoices_invoiceTest extends openpsa_testcase
{
    public function testCRUD()
    {
        midcom::get('auth')->request_sudo('org.openpsa.invoices');
        $invoice = new org_openpsa_invoices_invoice_dba();
        $next_number = $invoice->generate_invoice_number();
        $this->assertTrue(is_int($next_number));
        $invoice->number = $next_number;
        $stat = $invoice->create();
        $this->assertTrue($stat);
        $this->register_object($invoice);
        $this->assertEquals($next_number + 1, $invoice->generate_invoice_number());

        $sent = time();
        $date = time() - (3600 * 24);
        $invoice->sent = $sent;
        $invoice->date = $date;
        $stat = $invoice->update();
        $this->assertTrue($stat);
        $invoice->refresh();
        $expected_due = ($invoice->get_default_due() * 3600 * 24) + $date;
        $this->assertEquals($expected_due, $invoice->due);

        $stat = $invoice->delete();
        $this->assertTrue($stat);
        $this->assertEquals($next_number, $invoice->generate_invoice_number());

        midcom::get('auth')->drop_sudo();
    }

    /**
     * @depends testCRUD
     */
    public function testRecalculate_invoice_items()
    {
        $invoice = $this->create_object('org_openpsa_invoices_invoice_dba');

        $salesproject = $this->create_object('org_openpsa_sales_salesproject_dba');
        $deliverable_attributes = array
        (
            'salesproject' => $salesproject->id,
            'pricePerUnit' => 10
        );
        $deliverable = $this->create_object('org_openpsa_sales_salesproject_deliverable_dba', $deliverable_attributes);

        $task_attributes = array
        (
            'agreement' => $deliverable->id,
            'project' => $salesproject->id
        );
        $task1 = $this->create_object('org_openpsa_projects_task_dba', $task_attributes);
        $task2 = $this->create_object('org_openpsa_projects_task_dba', $task_attributes);

        $report_attributes = array
        (
            'invoice' => $invoice->id,
            'hours' => 1,
            'invoiceable' => true,
            'task' => $task1->id
        );

        $report1 = $this->create_object('org_openpsa_projects_hour_report_dba', $report_attributes);
        $report_attributes['task'] = $task2->id;
        $report2 = $this->create_object('org_openpsa_projects_hour_report_dba', $report_attributes);

        midcom::get('auth')->request_sudo('org.openpsa.invoices');
        $invoice->_recalculate_invoice_items();
        $invoice->refresh();
        $this->assertEquals(20, $invoice->sum);
        $this->assertEquals(2, $this->_count_invoice_items($invoice->id));

        //Rinse and repeat to see if the result stays stable
        $invoice->_recalculate_invoice_items();
        $invoice->refresh();
        $this->assertEquals(20, $invoice->sum);
        $this->assertEquals(2, $this->_count_invoice_items($invoice->id));

        $report1->hours = 2;
        $report1->update();
        $invoice->_recalculate_invoice_items();
        $invoice->refresh();
        $this->assertEquals(30, $invoice->sum);
        $this->assertEquals(2, $this->_count_invoice_items($invoice->id));

        $report2->invoiceable = false;
        $report2->update();

        $invoice->_recalculate_invoice_items(array($task1->id));
        $invoice->refresh();
        $this->assertEquals(30, $invoice->sum);
        $this->assertEquals(2, $this->_count_invoice_items($invoice->id));

        $invoice->_recalculate_invoice_items(array($task2->id));
        $invoice->refresh();
        $this->assertEquals(20, $invoice->sum);
        $this->assertEquals(2, $this->_count_invoice_items($invoice->id));

        self::delete_linked_objects('org_openpsa_invoices_invoice_item_dba', 'task', $task1->id);
        self::delete_linked_objects('org_openpsa_invoices_invoice_item_dba', 'task', $task2->id);

        midcom::get('auth')->drop_sudo();
    }

    private function _count_invoice_items($invoice_id)
    {
        $mc = org_openpsa_invoices_invoice_item_dba::new_collector('invoice', $invoice_id);
        $mc->execute();
        return sizeof($mc->list_keys());
    }
}
?>