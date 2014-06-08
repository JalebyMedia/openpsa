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
class org_openpsa_sales_handler_deliverable_adminTest extends openpsa_testcase
{
    protected static $_person;
    protected static $_salesproject;
    protected static $_product;

    public static function setUpBeforeClass()
    {
        self::$_person = self::create_user(true);
        self::$_salesproject = self::create_class_object('org_openpsa_sales_salesproject_dba');
        $product_group = self::create_class_object('org_openpsa_products_product_group_dba');
        self::$_product = self::create_class_object('org_openpsa_products_product_dba', array('productGroup' => $product_group->id));
    }

    public function testHandler_edit()
    {
        midcom::get()->auth->request_sudo('org.openpsa.sales');

        $deliverable_attributes = array
        (
            'salesproject' => self::$_salesproject->id,
            'product' => self::$_product->id,
        );

        $deliverable = $this->create_object('org_openpsa_sales_salesproject_deliverable_dba', $deliverable_attributes);
        $deliverable->set_parameter('midcom.helper.datamanager2', 'schema_name', 'subscription');

        $year = date('Y') + 1;
        $start = strtotime($year . '-10-15 00:00:00');

        $at_parameters = array
        (
            'arguments' => array
            (
                'deliverable' => $deliverable->guid,
                'cycle' => 1,
            ),
            'start' => $start,
            'component' => 'org.openpsa.sales',
            'method' => 'new_subscription_cycle'
        );

        $at_entry = $this->create_object('midcom_services_at_entry_dba', $at_parameters);
        org_openpsa_relatedto_plugin::create($at_entry, 'midcom.services.at', $deliverable, 'org.openpsa.sales');

        $data = $this->run_handler('org.openpsa.sales', array('deliverable', 'edit', $deliverable->guid));
        $this->assertEquals('deliverable_edit', $data['handler_id']);

        $group = $data['controller']->formmanager->form->getElement('next_cycle');

        $this->assertTrue($group instanceof HTML_Quickform_group, ' next cycle widget missing');
        $elements = $group->getElements();
        $this->assertEquals($year . '-10-15', $elements[0]->getValue());

        $formdata = array
        (
            'next_cycle_date' => '',
            'title' => 'test',
            'start_date' => '2012-10-10',
            'end_date' => $year . '-10-10'
        );

        $url = $this->submit_dm2_form('controller', $formdata, 'org.openpsa.sales', array('deliverable', 'edit', $deliverable->guid));

        $this->assertEquals('deliverable/' . $deliverable->guid . '/', $url);
        $this->assertEquals(0, count($deliverable->get_at_entries()));

        midcom::get()->auth->drop_sudo();
    }
}
?>