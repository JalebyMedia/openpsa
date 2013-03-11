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
class midcom_admin_user_handler_group_createTest extends openpsa_testcase
{
    public function testHandler_create()
    {
        midcom::get('auth')->request_sudo('midcom.admin.user');

        $data = $this->run_handler('net.nehmer.static', array('__mfa', 'asgard_midcom.admin.user', 'group', 'create'));
        $this->assertEquals('____mfa-asgard_midcom.admin.user-group_create', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }
}
?>