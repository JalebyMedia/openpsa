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
class midgard_admin_asgard_handler_object_manageTest extends openpsa_testcase
{
    protected static $_object;

    public static function setUpBeforeClass()
    {
        self::$_object = self::create_class_object('midcom_db_topic');
    }

    public function testHandler_view()
    {
        $this->create_user(true);
        midcom::get('auth')->request_sudo('midgard.admin.asgard');

        $data = $this->run_handler('net.nehmer.static', array('__mfa', 'asgard', 'object', 'view', self::$_object->guid));
        $this->assertEquals('____mfa-asgard-object_view', $data['handler_id']);
        $output = $this->show_handler($data);
        $this->assertRegExp('/class="midcom_helper_datamanager2_view"/', $output);
        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_edit()
    {
        $this->create_user(true);
        midcom::get('auth')->request_sudo('midgard.admin.asgard');

        $data = $this->run_handler('net.nehmer.static', array('__mfa', 'asgard', 'object', 'edit', self::$_object->guid));
        $this->assertEquals('____mfa-asgard-object_edit', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_copy()
    {
        $this->create_user(true);
        midcom::get('auth')->request_sudo('midgard.admin.asgard');

        $data = $this->run_handler('net.nehmer.static', array('__mfa', 'asgard', 'object', 'copy', self::$_object->guid));
        $this->assertEquals('____mfa-asgard-object_copy', $data['handler_id']);

        $formdata = array();

        $url = $this->submit_dm2_form('controller', $formdata, 'net.nehmer.static', array('__mfa', 'asgard', 'object', 'copy', self::$_object->guid));

        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_copy_tree()
    {
        $this->create_object('midcom_db_topic', array('up' => self::$_object->id));
        $this->create_user(true);
        midcom::get('auth')->request_sudo('midgard.admin.asgard');

        $data = $this->run_handler('net.nehmer.static', array('__mfa', 'asgard', 'object', 'copy', 'tree', self::$_object->guid));
        $this->assertEquals('____mfa-asgard-object_copy_tree', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_create_chooser()
    {
        $this->create_user(true);
        midcom::get('auth')->request_sudo('midgard.admin.asgard');

        $data = $this->run_handler('net.nehmer.static', array('__mfa', 'asgard', 'object', 'create', 'chooser', 'midgard_article'));
        $this->assertEquals('____mfa-asgard-object_create_chooser', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_create()
    {
        $this->create_user(true);
        midcom::get('auth')->request_sudo('midgard.admin.asgard');

        $data = $this->run_handler('net.nehmer.static', array('__mfa', 'asgard', 'object', 'create', 'midgard_article', self::$_object->guid));
        $this->assertEquals('____mfa-asgard-object_create', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_create_toplevel()
    {
        $this->create_user(true);
        midcom::get('auth')->request_sudo('midgard.admin.asgard');

        $data = $this->run_handler('net.nehmer.static', array('__mfa', 'asgard', 'object', 'create', 'midgard_topic'));
        $this->assertEquals('____mfa-asgard-object_create_toplevel', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_delete()
    {
        $this->create_user(true);
        midcom::get('auth')->request_sudo('midgard.admin.asgard');

        $data = $this->run_handler('net.nehmer.static', array('__mfa', 'asgard', 'object', 'delete', self::$_object->guid));
        $this->assertEquals('____mfa-asgard-object_delete', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }

}
?>