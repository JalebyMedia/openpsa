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
class org_openpsa_mypage_handler_workingonTest extends openpsa_testcase
{
    public static function setUpBeforeClass()
    {
        self::create_user(true);
    }

    public function testHandler_workingon()
    {
        $data = $this->run_handler('org.openpsa.mypage', array('workingon'));
        $this->assertEquals('workingon', $data['handler_id']);
    }

    public function testHandler_weekreview_redirect()
    {
        $project = $this->create_object('org_openpsa_projects_project');
        $task = $this->create_object('org_openpsa_projects_task_dba', array('project' => $project->id));

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = array
        (
            'task' => $task->id,
            'description' => 'test',
            'action' => 'stop',
        );

        $url = $this->run_relocate_handler('org.openpsa.mypage', array('workingon', 'set'));
        $this->assertEquals('workingon/', $url);
    }
}
?>