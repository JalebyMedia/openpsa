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
class org_openpsa_user_handler_person_createTest extends openpsa_testcase
{
    protected static $_user;

    public static function setUpBeforeClass()
    {
        self::$_user = self::create_user(true);
    }

    public function test_handler_create()
    {
        midcom::get()->auth->request_sudo('org.openpsa.user');

        $data = $this->run_handler('org.openpsa.user', array('create'));
        $this->assertEquals('user_create', $data['handler_id']);

        $username = uniqid(__FUNCTION__);
        $formdata = array
        (
            'firstname' => __CLASS__ . '::' . __FUNCTION__,
            'lastname' => __CLASS__ . '::' . __FUNCTION__,
            'email' => __FUNCTION__ . '@openpsa2.org',
            'org_openpsa_user_person_account_password_switch' => '1',
            'username' => $username,
            'password' => array
            (
                'password_input' => 'p@ssword123'
            ),
            'send_welcome_mail' => '1'
        );

        $url = $this->submit_dm2_form('controller', $formdata, 'org.openpsa.user', array('create'));
        $tokens = explode('/', trim($url, '/'));

        $guid = end($tokens);
        $person = new midcom_db_person($guid);

        $this->assertEquals(__CLASS__ . '::' . __FUNCTION__, $person->firstname);
        $this->assertEquals(__CLASS__ . '::' . __FUNCTION__, $person->lastname);

        $account = new midcom_core_account($person);
        $this->assertEquals($username, $account->get_username());

        midcom::get()->auth->drop_sudo();
    }
}
