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
class midcom_services_auth_mainTest extends openpsa_testcase
{
    public function test_can_do()
    {
        $topic = $this->create_object('midcom_db_topic');
        $person = $this->create_user();
        $user = new midcom_core_user($person);

        $auth = new midcom_services_auth;
        $auth->initialize();

        $this->assertFalse($auth->can_do('midgard:read', null));

        $this->assertTrue($auth->can_do('midgard:read', $topic));
        $this->assertFalse($auth->can_do('midgard:delete', $topic));

        $auth->admin = true;
        $this->assertFalse($auth->can_do('midgard:delete', $topic));

        $auth->user = $user;
        $this->assertTrue($auth->can_do('midgard:delete', $topic));

        $auth->admin = false;
        $this->assertFalse($auth->can_do('midgard:delete', $topic));

        $person2 = $this->create_user();
        $user2 = new midcom_core_user($person2);
        $topic2 = $this->create_object('midcom_db_topic');
        midcom::get('auth')->request_sudo('midcom.core');
        $topic2->set_privilege('midgard:delete', $user2->id, MIDCOM_PRIVILEGE_ALLOW);
        midcom::get('auth')->drop_sudo();
        $auth->user = $user2;

        $this->assertTrue($auth->can_do('midgard:delete', $topic2));
    }

    public function test_can_user_do()
    {
        $person = $this->create_user();
        $user = new midcom_core_user($person);

        $auth = new midcom_services_auth;
        $auth->initialize();

        $this->assertTrue($auth->can_user_do('midgard:read'));
        $this->assertFalse($auth->can_user_do('midgard:create'));

        $auth->admin = true;
        $this->assertTrue($auth->can_user_do('midgard:create'));

        $auth->admin = false;
        $auth->request_sudo('midcom.core');
        $this->assertTrue($auth->can_user_do('midgard:create'));
        $auth->drop_sudo();

        $auth->user = $user;
        $this->assertFalse($auth->can_user_do('midgard:create'));

        $person2 = $this->create_user();
        $user2 = new midcom_core_user($person2);
        midcom::get('auth')->request_sudo('midcom.core');
        $person2->set_privilege('midgard:create', 'SELF', MIDCOM_PRIVILEGE_ALLOW);
        midcom::get('auth')->drop_sudo();

        $this->assertTrue($auth->can_user_do('midgard:create', $user2));
    }

    public function test_get_privileges()
    {
        $person = $this->create_user();
        $user = new midcom_core_user($person);
        $topic = $this->create_object('midcom_db_topic');

        $auth = new midcom_services_auth;
        $auth->initialize();

        $privileges = $auth->get_privileges($topic, $user);

        $this->assertTrue(sizeof($privileges) > 0);
    }

    public function test_request_sudo()
    {
        $auth = new midcom_services_auth;
        $auth->initialize();

        $context = midcom_core_context::get();
        $context->set_key(MIDCOM_CONTEXT_COMPONENT, 'midcom.admin.folder');

        $this->assertTrue($auth->request_sudo());
        $this->assertTrue($auth->is_component_sudo());
        $auth->drop_sudo();
        $this->assertFalse($auth->is_component_sudo());
        $this->assertFalse($auth->request_sudo(''));
        $this->assertFalse($auth->is_component_sudo());
        $this->assertTrue($auth->request_sudo('some_string'));
        $auth->drop_sudo();

        $GLOBALS['midcom_config']['auth_allow_sudo'] = false;
        $this->assertFalse($auth->request_sudo());
        $GLOBALS['midcom_config']['auth_allow_sudo'] = true;
    }
}
?>