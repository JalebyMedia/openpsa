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
    require_once(OPENPSA_TEST_ROOT . 'rootfile.php');
}

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class midcom_admin_folder_handler_approvalsTest extends openpsa_testcase
{
    public function testHandler_approve()
    {
        midcom::get('auth')->request_sudo('midcom.admin.folder');

        $node = self::get_component_node('net.nehmer.static');

        $_REQUEST = array
        (
            'guid' => $node->guid,
            'return_to' => 'TEST'
        );
        $url = $this->run_relocate_handler('net.nehmer.static', array('__ais', 'folder', 'approve'));
        $this->assertEquals('TEST', $url);

        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_unapprove()
    {
        midcom::get('auth')->request_sudo('midcom.admin.folder');

        $node = self::get_component_node('net.nehmer.static');

        $_REQUEST = array
        (
            'guid' => $node->guid,
            'return_to' => 'TEST'
        );
        $url = $this->run_relocate_handler('net.nehmer.static', array('__ais', 'folder', 'unapprove'));
        $this->assertEquals('TEST', $url);

        midcom::get('auth')->drop_sudo();
    }

}
?>