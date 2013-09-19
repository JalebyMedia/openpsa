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
class org_openpsa_calendar_eventTest extends openpsa_testcase
{
    public function testCRUD()
    {
        midcom::get('auth')->request_sudo('org.openpsa.calendar');

        $event = new org_openpsa_calendar_event_dba();
        $event->_use_activitystream = false;
        $event->_use_rcs = false;

        $stat = $event->create();
        $this->assertTrue($stat);
        $this->register_object($event);

        $root_event = org_openpsa_calendar_interface::find_root_event();
        $this->assertEquals($root_event->id, $event->up);

        $stat = $event->update();
        $this->assertFalse($stat);

        $start = $this->_mktime(time() - (60 * 60));
        $event->start = $start;

        $stat = $event->update();
        $this->assertFalse($stat);

        $end = $this->_mktime(time() + (60 * 60));
        $event->end = $end;

        $stat = $event->update();
        $this->assertTrue($stat);

        $this->assertEquals($start + 1, $event->start);
        $this->assertEquals($end, $event->end);

        $stat = $event->delete();
        $this->assertTrue($stat);

        midcom::get('auth')->drop_sudo();
     }

    private function _mktime($timestamp)
    {
        return mktime(date('G', $timestamp),
                                date('i', $timestamp),
                                0,
                                date('n', $timestamp),
                                date('j', $timestamp),
                                date('Y', $timestamp));
    }
}
?>
