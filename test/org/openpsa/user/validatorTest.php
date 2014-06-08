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
class org_openpsa_user_validatorTest extends openpsa_testcase
{
    protected static $_user;

    public static function setUpBeforeClass()
    {
        self::$_user = self::create_user();
    }

    public function testValidate_edit_form()
    {
        $val = new org_openpsa_user_validator;

        $person = self::create_user(true);
        $account = midcom_core_account::get($person);

        // this should work
        $fields = array(
            "person" => $person->guid,
            "username" => $account->get_username(),
            "current_password" => $person->extra
        );
        $this->assertTrue($val->validate_edit_form($fields));

        // try changing the username
        $fields = array(
            "person" => $person->guid,
            "username" => $account->get_username(),
            "current_password" => "abc"
        );
        $result = $val->validate_edit_form($fields);
        $this->assertTrue(is_array($result));
        $this->assertTrue(array_key_exists("current_password", $result));

        // now, use sudo..
        midcom::get()->auth->request_sudo("midcom.core");
        // try setting another password
        $fields = array(
            "person" => $person->guid,
            "username" => $account->get_username(),
            "current_password" => "abc"
        );
        $this->assertTrue($val->validate_edit_form($fields));

        // try using another username
        $fields = array(
            "person" => $person->guid,
            "username" => uniqid(__FUNCTION__ . "Bob"),
            "current_password" => $account->get_password()
        );
        $this->assertTrue($val->validate_edit_form($fields));
        midcom::get()->auth->drop_sudo();
    }

    public function testUsername_exists()
    {
        $val = new org_openpsa_user_validator;

        $person = self::create_user();
        $account = midcom_core_account::get($person);

        // try valid username
        $this->assertTrue($val->username_exists(array("username" => $account->get_username())));

        // try invalid username
        $result = $val->username_exists(array("username" => uniqid(__FUNCTION__ . "FAKE_BOB")));
        $this->assertTrue(is_array($result));
        $this->assertTrue(array_key_exists("username", $result));
    }

    public function testEmail_exists()
    {
        $val = new org_openpsa_user_validator;

        $person = self::create_user();

        // try invalid email
        $result = $val->email_exists(array("email" => uniqid(__FUNCTION__ . "-fake-mail-") . "@nowhere.cc"));
        $this->assertTrue(is_array($result));
        $this->assertTrue(array_key_exists("email", $result));

        // try valid email
        $email = uniqid(__FUNCTION__ . "-user-") . "@nowhere.cc";
        $person->email = $email;
        $person->update();

        $this->assertTrue($val->email_exists(array("email" => $email)));
    }

    public function testEmail_and_username_exist()
    {
        $val = new org_openpsa_user_validator;

        $person = self::create_user();
        $account = midcom_core_account::get($person);

        // try invalid combination
        $fields = array(
            "username" => $account->get_username(),
            "email" => uniqid(__FUNCTION__ . "-fake-mail-") . "@nowhere.cc"
        );

        $result = $val->email_and_username_exist($fields);
        $this->assertTrue(is_array($result));
        $this->assertTrue(array_key_exists("username", $result));

        // use invalid username as well
        $fields["username"] = uniqid(__FUNCTION__ . "-fake-user-");
        $this->assertTrue(is_array($result));
        $this->assertTrue(array_key_exists("username", $result));

        // try valid combination
        $email = uniqid(__FUNCTION__ . "-user-") . "@nowhere.cc";
        $person->email = $email;
        $person->update();

        $fields = array(
            "username" => $account->get_username(),
            "email" => $email
        );

        $this->assertTrue($val->email_and_username_exist($fields));
    }
}
?>