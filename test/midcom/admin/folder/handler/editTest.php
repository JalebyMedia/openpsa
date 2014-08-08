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
class midcom_admin_folder_handler_editTest extends openpsa_testcase
{
    public function testHandler_create()
    {
        midcom::get()->auth->request_sudo('midcom.admin.folder');

        $data = $this->run_handler('net.nehmer.static', array('__ais', 'folder', 'create'));
        $this->assertEquals('____ais-folder-create', $data['handler_id']);

        midcom::get()->auth->drop_sudo();
    }

    public function testHandler_edit()
    {
        midcom::get()->auth->request_sudo('midcom.admin.folder');

        $data = $this->run_handler('net.nehmer.static', array('__ais', 'folder', 'edit'));
        $this->assertEquals('____ais-folder-edit', $data['handler_id']);

        midcom::get()->auth->drop_sudo();
    }
}
