<?php
/**
 * @package midcom.db
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM level replacement for the Midgard Group record with framework support.
 *
 * @package midcom.db
 */
class midcom_db_group extends midcom_core_dbaobject
{
    public $__midcom_class_name__ = __CLASS__;
    public $__mgdschema_class_name__ = 'midgard_group';

    function get_label()
    {
        return $this->official;
    }

    /**
     * Updates all computed members.
     */
    public function _on_loaded()
    {
        if (empty($this->official))
        {
            $this->official = $this->name;
        }

        if (empty($this->official))
        {
            $this->official = "Group #{$this->id}";
        }
    }

    /**
     * Helper function, adds the given person to this group. The current user must have
     * midgard:create privileges on this object for this to succeed. If the person is
     * already a member of this group, nothing is done.
     *
     * @param midcom_db_person The person to add.
     * @return boolean Indicating success.
     */
    function add_member($person)
    {
        $this->require_do('midgard:create');

        if ($this->is_member($person))
        {
            return true;
        }

        $member = new midcom_db_member();
        $member->gid = $this->id;
        $member->uid = $person->id;
        if (! $member->create())
        {
            return false;
        }

        // Adjust privileges, owner is the group in question.
        $member->set_privilege('midgard:owner', "group:{$this->guid}");
        $member->unset_privilege('midgard:owner');

        return true;
    }

    /**
     * Checks whether the given user is a member of this group.
     *
     * @param midcom_db_person The person to check.
     * @return boolean Indicating membership.
     */
    function is_member($person)
    {
        $qb = midcom_db_member::new_query_builder();
        $qb->add_constraint('gid', '=', $this->id);
        $qb->add_constraint('uid', '=', $person->id);
        $result = $qb->count();
        if($result == 0)
        {
            return false;
        }
        return true;
    }
}
?>