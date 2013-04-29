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
class org_openpsa_projects_hour_reportTest extends openpsa_testcase
{
    protected static $_task;
    protected static $_project;

    public static function setUpBeforeClass()
    {
        self::$_project = self::create_class_object('org_openpsa_projects_project');
        self::$_task = self::create_class_object('org_openpsa_projects_task_dba', array('project' => self::$_project->id));
    }

    public function testCRUD()
    {
        midcom::get('auth')->request_sudo('org.openpsa.projects');
        $report = new org_openpsa_projects_hour_report_dba();
        $report->task = self::$_task->id;
        $report->hours = 2.5;
        $stat = $report->create();
        $this->assertTrue($stat);
        $this->register_object($report);

        $parent = $report->get_parent();
        $this->assertEquals($parent->guid, self::$_task->guid);

        self::$_task->refresh();
        $this->assertEquals(self::$_task->reportedHours, 2.5);
        $task_hours = self::$_project->get_task_hours();
        $this->assertEquals($task_hours['reportedHours'], 2.5);

        $report->invoiceable = true;
        $report->hours = 3.5;
        $stat = $report->update();

        $this->assertTrue($stat);
        self::$_task->refresh();
        $this->assertEquals(self::$_task->invoiceableHours, 3.5);
        $task_hours = self::$_project->get_task_hours();
        $this->assertEquals($task_hours['reportedHours'], 3.5);

        $stat = $report->delete();
        $this->assertTrue($stat);

        self::$_task->refresh();
        $this->assertEquals(self::$_task->reportedHours, 0);
        $task_hours = self::$_project->get_task_hours();
        $this->assertEquals($task_hours['reportedHours'], 0);

        midcom::get('auth')->drop_sudo();
    }

    public function test_get_parent()
    {
        $report = $this->create_object('org_openpsa_projects_hour_report_dba', array('task' => self::$_task->id));
        $parent = $report->get_parent();
        $this->assertEquals(self::$_task->guid, $parent->guid);
    }

    public function tearDown()
    {
        self::delete_linked_objects('org_openpsa_projects_hour_report_dba', 'task', self::$_task->id);
    }
}
?>