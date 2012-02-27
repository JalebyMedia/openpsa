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
class org_openpsa_expenses_handler_hours_adminTest extends openpsa_testcase
{
    protected static $_task;
    protected static $_report;

    public static function setUpBeforeClass()
    {
        $project = self::create_class_object('org_openpsa_projects_project');
        self::$_task = self::create_class_object('org_openpsa_projects_task_dba', array('project' => $project->id));
        self::$_report = self::create_class_object('org_openpsa_projects_hour_report_dba', array('task' => self::$_task->id));
        self::create_user(true);
    }

    public function testHandler_hours_edit()
    {
        midcom::get('auth')->request_sudo('org.openpsa.expenses');

        $data = $this->run_handler('org.openpsa.expenses', array('hours', 'edit', self::$_report->guid));
        $this->assertEquals('hours_edit', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_hours_delete()
    {
        midcom::get('auth')->request_sudo('org.openpsa.expenses');

        $data = $this->run_handler('org.openpsa.expenses', array('hours', 'delete', self::$_report->guid));
        $this->assertEquals('hours_delete', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_hours_create()
    {
        midcom::get('auth')->request_sudo('org.openpsa.expenses');

        $data = $this->run_handler('org.openpsa.expenses', array('hours', 'create', 'hour_report'));
        $this->assertEquals('hours_create', $data['handler_id']);

        $person = $this->create_object('midcom_db_person');

        $formdata = array
        (
            'description' => __CLASS__ . '::' . __FUNCTION__,
            'hours' => '2',
            'org_openpsa_expenses_person_chooser_selections' => array($person->id),
            'org_openpsa_expenses_task_chooser_selections' => array(self::$_task->id),
        );

        $url = $this->submit_dm2_form('controller', $formdata, 'org.openpsa.expenses', array('hours', 'create', 'hour_report'));

        $qb = org_openpsa_projects_hour_report_dba::new_query_builder();
        $qb->add_constraint('task', '=', self::$_task->id);
        $qb->add_constraint('description', '=', __CLASS__ . '::' . __FUNCTION__);
        $results = $qb->execute();
        $this->register_objects($results);
        $this->assertEquals(1, sizeof($results));
        $this->assertEquals('hours/task/' . self::$_task->guid . '/', $url);

        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_hours_create_task()
    {
        midcom::get('auth')->request_sudo('org.openpsa.expenses');

        $data = $this->run_handler('org.openpsa.expenses', array('hours', 'create', 'hour_report', self::$_task->guid));
        $this->assertEquals('hours_create_task', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }
}
?>