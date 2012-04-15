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
class org_openpsa_projects_handler_project_crudTest extends openpsa_testcase
{
    protected static $_project;

    public static function setupBeforeClass()
    {
        self::create_user(true);

        self::$_project = self::create_class_object('org_openpsa_projects_project');
    }

    public function testHandler_create()
    {
        midcom::get('auth')->request_sudo('org.openpsa.projects');

        $data = $this->run_handler('org.openpsa.projects', array('project', 'new'));
        $this->assertEquals('project-new', $data['handler_id']);

        $this->show_handler($data);
        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_read()
    {
        midcom::get('auth')->request_sudo('org.openpsa.projects');

        $data = $this->run_handler('org.openpsa.projects', array('project', self::$_project->guid));
        $this->assertEquals('project',  $data['handler_id']);

        $this->show_handler($data);
        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_update()
    {
        midcom::get('auth')->request_sudo('org.openpsa.projects');

        $data = $this->run_handler('org.openpsa.projects', array('project', 'edit', self::$_project->guid));
        $this->assertEquals('project-edit', $data['handler_id']);

        $this->show_handler($data);
        midcom::get('auth')->drop_sudo();
    }
}
?>