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
class org_openpsa_contacts_handler_searchTest extends openpsa_testcase
{
    protected static $_person;

    public static function setUpBeforeClass()
    {
        self::$_person = self::create_user(true);
    }

    public function testHandler_search()
    {
        midcom::get()->auth->request_sudo('org.openpsa.contacts');

        $data = $this->run_handler('org.openpsa.contacts', array('search'));
        $this->assertEquals('search', $data['handler_id']);

        $_GET = array('query' => 'firstname lastname');
        $data = $this->run_handler('org.openpsa.contacts', array('search'));
        $this->show_handler($data);
        midcom::get()->auth->drop_sudo();
    }

    public function testHandler_search_type()
    {
        midcom::get()->auth->request_sudo('org.openpsa.contacts');

        $data = $this->run_handler('org.openpsa.contacts', array('search', 'foaf'));
        $this->assertEquals('search_type', $data['handler_id']);

        $this->show_handler($data);
        midcom::get()->auth->drop_sudo();
    }
}
