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
class org_openpsa_sales_handler_deliverable_addTest extends openpsa_testcase
{
    protected static $_person;

    public static function setUpBeforeClass()
    {
        self::$_person = self::create_user(true);
    }

    public function testHandler_add()
    {
        midcom::get('auth')->request_sudo('org.openpsa.sales');

        $salesproject = $this->create_object('org_openpsa_sales_salesproject_dba');
        $product_group = $this->create_object('org_openpsa_products_product_group_dba');
        $product_attributes = array
        (
            'productGroup' => $product_group->id,
            'name' => 'TEST_' . __CLASS__ . '_' . time(),
        );
        $product = $this->create_object('org_openpsa_products_product_dba', $product_attributes);

        $_SERVER['REQUEST_METHOD'] = 'POST';

        $_POST = array
        (
            'product' => $product->id,
        );

        $data = $this->run_handler('org.openpsa.sales', array('deliverable', 'add', $salesproject->guid));
        $this->assertEquals('deliverable_add', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }
}
?>