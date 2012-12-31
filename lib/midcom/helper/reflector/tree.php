<?php
/**
 * @package midcom.helper.reflector
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * The Grand Unified Reflector, Tree information
 * @package midcom.helper.reflector
 */
class midcom_helper_reflector_tree extends midcom_helper_reflector
{
    public static function &get($src)
    {
        if (is_object($src))
        {
            $classname = get_class($src);
        }
        else
        {
            $classname = $src;
        }
        if (!isset($GLOBALS['midcom_helper_reflector_tree_singletons'][$classname]))
        {
            $GLOBALS['midcom_helper_reflector_tree_singletons'][$classname] =  new midcom_helper_reflector_tree($src);
        }
        return $GLOBALS['midcom_helper_reflector_tree_singletons'][$classname];
    }

    /**
     * Creates a QB instance for get_root_objects and count_root_objects
     *
     * @access private
     */
    function &_root_objects_qb(&$deleted)
    {
        $schema_type =& $this->mgdschema_class;
        $root_classes = midcom_helper_reflector_tree::get_root_classes();
        if (!in_array($schema_type, $root_classes))
        {
            debug_add("Type {$schema_type} is not a \"root\" type", MIDCOM_LOG_ERROR);
            $x = false;
            return $x;
        }

        if ($deleted)
        {
            $qb = new midgard_query_builder($schema_type);
        }
        else
        {
            // Figure correct MidCOM DBA class to use and get midcom QB
            $qb = false;
            $midcom_dba_classname = midcom::get('dbclassloader')->get_midcom_class_name_for_mgdschema_object($this->_dummy_object);
            if (empty($midcom_dba_classname))
            {
                debug_add("MidCOM DBA does not know how to handle {$schema_type}", MIDCOM_LOG_ERROR);
                $x = false;
                return $x;
            }
            if (!midcom::get('dbclassloader')->load_mgdschema_class_handler($midcom_dba_classname))
            {
                debug_add("Failed to load the handling component for {$midcom_dba_classname}, cannot continue.", MIDCOM_LOG_ERROR);
                $x = false;
                return $x;
            }
            $qb_callback = array($midcom_dba_classname, 'new_query_builder');
            if (!is_callable($qb_callback))
            {
                debug_add("Static method {$midcom_dba_classname}::new_query_builder() is not callable", MIDCOM_LOG_ERROR);
                $x = false;
                return $x;
            }
            $qb = call_user_func($qb_callback);
        }

        // Sanity-check
        if (!$qb)
        {
            debug_add("Could not get QB for type '{$schema_type}'", MIDCOM_LOG_ERROR);
            $x = false;
            return $x;
        }
        // Deleted constraints
        if ($deleted)
        {
            $qb->include_deleted();
            $qb->add_constraint('metadata.deleted', '<>', 0);
        }

        // Figure out constraint to use to get root level objects
        $upfield = midgard_object_class::get_property_up($schema_type);
        if (!empty($upfield))
        {
            $ref =& $this->_mgd_reflector;
            $uptype = $ref->get_midgard_type($upfield);
            switch ($uptype)
            {
                case MGD_TYPE_STRING:
                case MGD_TYPE_GUID:
                    $qb->add_constraint($upfield, '=', '');
                    break;
                case MGD_TYPE_INT:
                case MGD_TYPE_UINT:
                    $qb->add_constraint($upfield, '=', 0);
                    break;
                default:
                    debug_add("Do not know how to handle upfield '{$upfield}' has type {$uptype}", MIDCOM_LOG_ERROR);
                    return false;
            }
        }
        return $qb;
    }

    /**
     * Get count of "root" objects for the class this reflector was instantiated for
     *
     * @param boolean $deleted whether to count (only) deleted or not-deleted objects
     * @return array of objects or false on failure
     * @see get_root_objects
     */
    function count_root_objects($deleted = false)
    {
        // Check against static calling
        if (empty($this->mgdschema_class))
        {
            debug_add('May not be called statically', MIDCOM_LOG_ERROR);
            return false;
        }
        // PONDER: Check for some generic user privilege instead  ??
        if (   $deleted
            && !midcom_connection::is_admin())
        {
            debug_add('Non-admins are not allowed to list deleted objects', MIDCOM_LOG_ERROR);
            return false;
        }

        $qb = $this->_root_objects_qb($deleted);
        if (!$qb)
        {
            debug_add('Could not get QB instance', MIDCOM_LOG_ERROR);
            return false;
        }

        $count = $qb->count();
        return $count;
    }

    function has_root_objects()
    {
        // Check against static calling
        if (empty($this->mgdschema_class))
        {
            debug_add('May not be called statically', MIDCOM_LOG_ERROR);
            return false;
        }
        // PONDER: Check for some generic user privilege instead  ??
        if (   $deleted
            && !midcom_connection::is_admin())
        {
            debug_add('Non-admins are not allowed to list deleted objects', MIDCOM_LOG_ERROR);
            return false;
        }

        $qb = $this->_root_objects_qb($deleted);
        if (!$qb)
        {
            debug_add('Could not get QB instance', MIDCOM_LOG_ERROR);
            return false;
        }
        $qb->set_limit(1);
        if ($qb->count())
        {
            unset($qb);
            return true;
        }
        unset($qb);
        return false;
    }

    /**
     * Get "root" objects for the class this reflector was instantiated for
     *
     * NOTE: deleted objects can only be listed as admin, also: they do not come
     * MidCOM DBA wrapped (since you cannot normally instantiate such object)
     *
     * @param boolean $deleted whether to get (only) deleted or not-deleted objects
     * @return array of objects or false on failure
     */
    public function get_root_objects($deleted = false)
    {
        // Check against static calling
        if (empty($this->mgdschema_class))
        {
            debug_add('May not be called statically', MIDCOM_LOG_ERROR);
            return false;
        }
        // PONDER: Check for some generic user privilege instead  ??
        if (   $deleted
            && !midcom_connection::is_admin())
        {
            debug_add('Non-admins are not allowed to list deleted objects', MIDCOM_LOG_ERROR);
            return false;
        }

        $qb = $this->_root_objects_qb($deleted);
        if (!$qb)
        {
            debug_add('Could not get QB instance', MIDCOM_LOG_ERROR);
            return false;
        }
        midcom_helper_reflector_tree::add_schema_sorts_to_qb($qb, $this->mgdschema_class);

        $ref = $this->get($this->mgdschema_class);

        $label_property = $ref->get_label_property();

        if (   is_string($label_property)
            && midcom::get('dbfactory')->property_exists($this->mgdschema_class, $label_property))
        {
            $qb->add_order($label_property);
        }
        else
        {
            $title_property = $ref->get_title_property(new $this->mgdschema_class());
            if (   is_string($title_property)
                && midcom::get('dbfactory')->property_exists($this->mgdschema_class, $title_property))
            {
                $qb->add_order($title_property);
            }
        }
        $objects = $qb->execute();

        return $objects;
    }

    /**
     * Statically callable method to determine if given object has children
     *
     * @param midgard_object &$object object to get children for
     * @param boolean $deleted whether to count (only) deleted or not-deleted objects
     * @return array multidimensional array (keyed by classname) of objects or false on failure
     */
    function has_child_objects(&$object, $deleted = false)
    {
        // PONDER: Check for some generic user privilege instead  ??
        if (   $deleted
            && !midcom_connection::is_admin())
        {
            debug_add('Non-admins are not allowed to list deleted objects', MIDCOM_LOG_ERROR);
            return false;
        }
        $resolver = new midcom_helper_reflector_tree($object);
        $child_classes = $resolver->get_child_classes();
        if (!$child_classes)
        {
            debug_add('resolver returned false (critical failure) from get_child_classes()', MIDCOM_LOG_ERROR);
            return false;
        }
        foreach ($child_classes as $schema_type)
        {
            $qb = $resolver->_child_objects_type_qb($schema_type, $object, $deleted);
            if (!$qb)
            {
                debug_add('resolver returned false (critical failure) from _child_objects_type_qb()', MIDCOM_LOG_ERROR);
                return false;
            }
            $qb->set_limit(1);
            if ($qb->count())
            {
                unset($child_classes, $schema_type, $qb, $resolver);
                return true;
            }
        }
        unset($child_classes, $schema_type, $qb, $resolver);
        return false;
    }

    /**
     * Statically callable method to count children of given object
     *
     * @param midgard_object &$object object to get children for
     * @param boolean $deleted whether to count (only) deleted or not-deleted objects
     * @return array multidimensional array (keyed by classname) of objects or false on failure
     */
    function count_child_objects(&$object, $deleted = false)
    {
        // PONDER: Check for some generic user privilege instead  ??
        if (   $deleted
            && !midcom_connection::is_admin())
        {
            debug_add('Non-admins are not allowed to list deleted objects', MIDCOM_LOG_ERROR);
            return false;
        }
        $resolver = new midcom_helper_reflector_tree($object);
        $child_classes = $resolver->get_child_classes();
        if (!$child_classes)
        {
            debug_add('resolver returned false (critical failure) from get_child_classes()', MIDCOM_LOG_ERROR);
            return false;
        }

        $child_counts = array();
        foreach ($child_classes as $schema_type)
        {
            $child_counts[$schema_type] = $resolver->_count_child_objects_type($schema_type, $object, $deleted);
        }
        return $child_counts;
    }

    /**
     * Method to get rendered path for object
     *
     * @param midgard_object &$object, the object to get path for
     * @param string $separator the string used to separate path components
     * @param GUID $stop_at in case we wish to stop resolving at certain object give guid here
     * @return string resolved path
     */
    public static function resolve_path(&$object, $separator = ' &gt; ', $stop_at = null)
    {
        static $cache = array();
        $cache_key = $object->guid . $separator . $stop_at;
        if (isset($cache[$cache_key]))
        {
            return $cache[$cache_key];
        }
        $parts = self::resolve_path_parts($object, $stop_at);
        $d = count($parts);
        $ret = '';
        foreach ($parts as $part)
        {
            $ret .= $part['label'];
            --$d;
            if ($d)
            {
                $ret .= $separator;
            }
        }
        unset($part, $d, $parts);
        $cache[$cache_key] = $ret;
        unset($ret);
        return $cache[$cache_key];
    }

    /**
     * Get path components for object
     *
     * @param midgard_object &$object, the object to get path for
     * @param GUID $stop_at in case we wish to stop resolving at certain object give guid here
     * @return array path components
     */
    public static function resolve_path_parts(&$object, $stop_at = null)
    {
        static $cache = array();
        $cache_key = $object->guid . $stop_at;
        if (isset($cache[$cache_key]))
        {
            return $cache[$cache_key];
        }

        $ret = array();
        $object_reflector =& midcom_helper_reflector::get($object);
        $part = array
        (
            'object' => $object,
            'label' => $object_reflector->get_object_label($object),
        );
        $ret[] = $part;
        unset($part, $object_reflector);

        $parent = self::get_parent($object);
        while (is_object($parent))
        {
            $parent_reflector =& midcom_helper_reflector::get($parent);
            $part = array
            (
                'object' => $parent,
                'label' => $parent_reflector->get_object_label($parent),
            );
            $ret[] = $part;
            unset($part, $parent_reflector);
            $parent = self::get_parent($parent);
        }
        unset($parent);

        $ret = array_reverse($ret);
        $cache[$cache_key] = $ret;
        unset($ret);
        return $cache[$cache_key];
    }

    /**
     * Get the parent object of given object
     *
     * Tries to utilize MidCOM DBA features first but can fallback on pure MgdSchema
     * as necessary
     *
     * NOTE: since this might fall back to pure MgdSchema never trust that MidCOM DBA features
     * are available, check for is_callable/method_exists first !
     *
     * @param midgard_object &$object the object to get parent for
     */
    public static function get_parent(&$object)
    {
        $parent_object = false;
        $dba_parent_callback = array($object, 'get_parent');
        if (is_callable($dba_parent_callback))
        {
            $parent_object = $object->get_parent();
            /**
             * The object might have valid reasons for returning empty value here, but we can't know if it's
             * because it's valid or because the get_parent* methods have not been overridden in the actually
             * used class
             */
        }

        return $parent_object;
    }

    function _get_parent_objectresolver(&$object, &$property)
    {
        $ref =& $this->_mgd_reflector;
        $target_class = $ref->get_link_name($property);
        $dummy_object = new $target_class();
        $midcom_dba_classname = midcom::get('dbclassloader')->get_midcom_class_name_for_mgdschema_object($dummy_object);
        if (!empty($midcom_dba_classname))
        {
            if (!midcom::get('dbclassloader')->load_mgdschema_class_handler($midcom_dba_classname))
            {
                debug_add("Failed to load the handling component for {$midcom_dba_classname}, cannot continue.", MIDCOM_LOG_ERROR);
                return false;
            }
            // DBA classes can supposedly handle their own typecasts correctly
            $parent_object = new $midcom_dba_classname($object->$property);
            return $parent_object;
        }
        debug_add("MidCOM DBA does not know how to handle {$target_class}, falling back to pure MgdSchema", MIDCOM_LOG_WARN);

        $linktype = $ref->get_midgard_type($property);
        switch ($linktype)
        {
            case MGD_TYPE_STRING:
            case MGD_TYPE_GUID:
                $parent_object = new $target_class((string)$object->$property);
                break;
            case MGD_TYPE_INT:
            case MGD_TYPE_UINT:
                $parent_object = new $target_class((int)$object->$property);
                break;
            default:
                debug_add("Do not know how to handle linktype {$linktype}", MIDCOM_LOG_ERROR);
                return false;
        }

        return $parent_object;
    }


    /**
     * Get children of given object
     *
     * @param midgard_object &$object object to get children for
     * @param boolean $deleted whether to get (only) deleted or not-deleted objects
     * @return array multidimensional array (keyed by classname) of objects or false on failure
     */
    public static function get_child_objects(&$object, $deleted = false)
    {
        // PONDER: Check for some generic user privilege instead  ??
        if (   $deleted
            && !midcom_connection::is_admin())
        {
            debug_add('Non-admins are not allowed to list deleted objects', MIDCOM_LOG_ERROR);
            return false;
        }
        $resolver = new midcom_helper_reflector_tree($object);
        $child_classes = $resolver->get_child_classes();
        if (!$child_classes)
        {
            if ($child_classes === false)
            {
                debug_add('resolver returned false (critical failure) from get_child_classes()', MIDCOM_LOG_ERROR);
            }
            return false;
        }

        //make sure children of the same type come out on top
        $i = 0;
        foreach ($child_classes as $child_class)
        {
            if (midcom::get('dbfactory')->is_a($object, $child_class))
            {
                unset($child_classes[$i]);
                array_unshift($child_classes, $child_class);
                break;
            }
            $i++;
        }

        $child_objects = array();
        foreach ($child_classes as $schema_type)
        {
            $type_children = $resolver->_get_child_objects_type($schema_type, $object, $deleted);
            // PONDER: check for boolean false as result ??
            if (empty($type_children))
            {
                unset($type_children);
                continue;
            }
            $child_objects[$schema_type] = $type_children;
            unset($type_children);
        }
        return $child_objects;
    }

    /**
     * Creates a QB instance for _get_child_objects_type and _count_child_objects_type
     */
    public function &_child_objects_type_qb(&$schema_type, &$for_object, $deleted)
    {
        if (empty($schema_type))
        {
            debug_add('Passed schema_type argument is empty, this is fatal', MIDCOM_LOG_ERROR);
            $x = false;
            return $x;
        }
        if (!is_object($for_object))
        {
            debug_add('Passed for_object argument is not object, this is fatal', MIDCOM_LOG_ERROR);
            $x = false;
            return $x;
        }
        if ($deleted)
        {
            $qb = new midgard_query_builder($schema_type);
        }
        else
        {
            $qb = false;

            // Figure correct MidCOM DBA class to use and get midcom QB
            $midcom_dba_classname = midcom::get('dbclassloader')->get_midcom_class_name_for_mgdschema_object($schema_type);
            if (empty($midcom_dba_classname))
            {
                debug_add("MidCOM DBA does not know how to handle {$schema_type}", MIDCOM_LOG_ERROR);
                return $qb;
            }

            if (!midcom::get('dbclassloader')->load_component_for_class($midcom_dba_classname))
            {
                debug_add("Failed to load the handling component for {$midcom_dba_classname}, cannot continue.", MIDCOM_LOG_ERROR);
                return $qb;
            }

            $qb_callback = array($midcom_dba_classname, 'new_query_builder');
            if (!is_callable($qb_callback))
            {
                debug_add("Static method {$midcom_dba_classname}::new_query_builder() is not callable", MIDCOM_LOG_ERROR);
                return $qb;
            }
            $qb = call_user_func($qb_callback);
        }

        // Sanity-check
        if (!$qb)
        {
            debug_add("Could not get QB for type '{$schema_type}'", MIDCOM_LOG_ERROR);
            $x = false;
            return $x;
        }
        // Deleted constraints
        if ($deleted)
        {
            $qb->include_deleted();
            $qb->add_constraint('metadata.deleted', '<>', 0);
        }

        // Figure out constraint(s) to use to get child objects
        $ref = new midgard_reflection_property($schema_type);

        $multiple_links = false;
        $linkfields = array();
        $linkfields['up'] = midgard_object_class::get_property_up($schema_type);
        $linkfields['parent'] = midgard_object_class::get_property_parent($schema_type);

        $object_baseclass = midcom_helper_reflector::resolve_baseclass(get_class($for_object));

        foreach ($linkfields as $link_type => $field)
        {
            if (empty($field))
            {
                // No such field for the object
                unset($linkfields[$link_type]);
                continue;
            }


            $linked_class = $ref->get_link_name($field);
            if (   empty($linked_class)
                && $ref->get_midgard_type($field) === MGD_TYPE_GUID)
            {
                // Guid link without class specification, valid for all classes
                continue;
            }
            if ($linked_class != $object_baseclass)
            {
                // This link points elsewhere
                unset($linkfields[$link_type]);
                continue;
            }
        }

        if (count($linkfields) === 0)
        {
            debug_add("Class '{$schema_type}' has no valid link properties pointing to class '" . get_class($for_object) . "', this should not happen here", MIDCOM_LOG_ERROR);
            $x = false;
            return $x;
        }

        if (count($linkfields) > 1)
        {
            $multiple_links = true;
            $qb->begin_group('OR');
        }

        foreach ($linkfields as $link_type => $field)
        {
            $field_type = $ref->get_midgard_type($field);
            $field_target = $ref->get_link_target($field);
            if (   empty($field_target)
                && $field_type === MGD_TYPE_GUID)
            {
                $field_target = 'guid';
            }

            if (   !$field_target
                || !isset($for_object->$field_target))
            {
                if ($multiple_links)
                {
                    $qb->end_group();
                }
                // Why return false ???
                $x = false;
                return $x;
            }
            switch ($field_type)
            {
                case MGD_TYPE_STRING:
                case MGD_TYPE_GUID:
                    $qb->add_constraint($field, '=', (string) $for_object->$field_target);
                    break;
                case MGD_TYPE_INT:
                case MGD_TYPE_UINT:
                    if ($link_type == 'up')
                    {
                        $qb->add_constraint($field, '=', (int) $for_object->$field_target);
                    }
                    else
                    {
                        $qb->begin_group('AND');
                            $qb->add_constraint($field, '=', (int) $for_object->$field_target);
                            // make sure we don't accidentally find other objects with the same id
                            $qb->add_constraint($field . '.guid', '=', (string) $for_object->guid);
                        $qb->end_group();
                    }
                    break;
                default:
                    debug_add("Do not know how to handle linked field '{$field}', has type {$field_type}", MIDCOM_LOG_INFO);
                    if ($multiple_links)
                    {
                        $qb->end_group();
                    }
                    // Why return false ???
                    $x = false;
                    return $x;
            }
        }

        if ($multiple_links)
        {
            $qb->end_group();
        }

        return $qb;
    }

    /**
     * Used by get_child_objects
     *
     * @return array of objects
     */
    public function _get_child_objects_type(&$schema_type, &$for_object, $deleted)
    {
        $qb = $this->_child_objects_type_qb($schema_type, $for_object, $deleted);
        if (!$qb)
        {
            debug_add('Could not get QB instance', MIDCOM_LOG_ERROR);
            return false;
        }

        // Sort by title and name if available
        midcom_helper_reflector_tree::add_schema_sorts_to_qb($qb, $schema_type);

        $objects = $qb->execute();

        return $objects;
    }

    /**
     * Used by count_child_objects
     *
     * @return array of objects
     */
    public function _count_child_objects_type(&$schema_type, &$for_object, $deleted)
    {
        $qb = $this->_child_objects_type_qb($schema_type, $for_object, $deleted);
        if (!$qb)
        {
            debug_add('Could not get QB instance', MIDCOM_LOG_ERROR);
            return false;
        }
        $count = $qb->count();
        return $count;
    }

    /**
     * Get the parent class of the class this reflector was instantiated for
     *
     * @return string class name (or false if the type has no parent)
     */
    function get_parent_class()
    {
        $parent_property = midgard_object_class::get_property_parent($this->mgdschema_class);
        if (!$parent_property)
        {
            return false;
        }
        $ref = new midgard_reflection_property($this->mgdschema_class);
        return $ref->get_link_name($parent_property);
    }

    /**
     * Get the child classes of the class this reflector was instantiated for
     *
     * @return array of class names (or false on critical failure)
     */
    function get_child_classes()
    {
        // Check against static calling
        if (   !isset($this->mgdschema_class)
            || empty($this->mgdschema_class))
        {
            debug_add('May not be called statically', MIDCOM_LOG_ERROR);
            return false;
        }
        static $child_classes_all = array();
        if (!isset($child_classes_all[$this->mgdschema_class]))
        {
            $child_classes_all[$this->mgdschema_class] = false;
        }
        $child_classes =& $child_classes_all[$this->mgdschema_class];

        if ($child_classes === false)
        {
            $child_classes = $this->_resolve_child_classes();
        }
        return $child_classes;
    }

    /**
     * Resolve the child classes of the class this reflector was instantiated for, used by get_child_classes()
     *
     * @return array of class names (or false on critical failure)
     */
    function _resolve_child_classes()
    {
        // Check against static calling
        if (   !isset($this->mgdschema_class)
            || empty($this->mgdschema_class))
        {
            debug_add('May not be called statically', MIDCOM_LOG_ERROR);
            return false;
        }

        $child_class_exceptions_neverchild = $this->_config->get('child_class_exceptions_neverchild');

        // Safety against misconfiguration
        if (!is_array($child_class_exceptions_neverchild))
        {
            debug_add("config->get('child_class_exceptions_neverchild') did not return array, invalid configuration ??", MIDCOM_LOG_ERROR);
            $child_class_exceptions_neverchild = array();
        }
        $child_classes = array();
        foreach (midcom_connection::get_schema_types() as $schema_type)
        {
            if (in_array($schema_type, $child_class_exceptions_neverchild))
            {
                // Special cases, don't treat as children for normal objects
                continue;
            }
            $parent_property = midgard_object_class::get_property_parent($schema_type);
            $up_property = midgard_object_class::get_property_up($schema_type);

            if (   !$this->_resolve_child_classes_links_back($parent_property, $schema_type, $this->mgdschema_class)
                && !$this->_resolve_child_classes_links_back($up_property, $schema_type, $this->mgdschema_class))
            {
                continue;
            }
            $child_classes[] = $schema_type;
        }

        // TODO: handle exceptions

        return $child_classes;
    }

    function _resolve_child_classes_links_back($property, $prospect_type, $schema_type)
    {
        if (empty($property))
        {
            return false;
        }

        $ref = new midgard_reflection_property($prospect_type);
        $link_class = $ref->get_link_name($property);
        if (   empty($link_class)
            && $ref->get_midgard_type($property) === MGD_TYPE_GUID)
        {
            return true;
        }
        if (midcom_helper_reflector::is_same_class($link_class, $schema_type))
        {
            return true;
        }
        return false;
    }

    /**
     * Get an array of "root level" classes, can (and should) be called statically
     *
     * @return array of classnames (or false on critical failure)
     */
    public static function get_root_classes()
    {
        static $root_classes = false;
        if (empty($root_classes))
        {
            $root_classes = self::_resolve_root_classes();
        }
        return $root_classes;
    }

    /**
     * Resolves the "root level" classes, used by get_root_classes()
     *
     * @return array of classnames (or false on critical failure)
     */
    private static function _resolve_root_classes()
    {
        $root_exceptions_notroot = midcom_baseclasses_components_configuration::get('midcom.helper.reflector', 'config')->get('root_class_exceptions_notroot');
        // Safety against misconfiguration
        if (!is_array($root_exceptions_notroot))
        {
            debug_add("config->get('root_class_exceptions_notroot') did not return array, invalid configuration ??", MIDCOM_LOG_ERROR);
            $root_exceptions_notroot = array();
        }
        $root_classes = array();
        foreach (midcom_connection::get_schema_types() as $schema_type)
        {
            if (substr($schema_type, 0, 2) == '__')
            {
                continue;
            }

            if (in_array($schema_type, $root_exceptions_notroot))
            {
                // Explicitly specified to not be root class, skip all heuristics
                continue;
            }

            // Class extensions mapping
            $schema_type = midcom_helper_reflector::class_rewrite($schema_type);

            // Make sure we only add classes once
            if (in_array($schema_type, $root_classes))
            {
                // Already listed
                continue;
            }

            $parent = midgard_object_class::get_property_parent($schema_type);
            if (!empty($parent))
            {
                // type has parent set, thus cannot be root type
                continue;
            }

            $dba_class = midcom::get('dbclassloader')->get_midcom_class_name_for_mgdschema_object($schema_type);
            if (!$dba_class)
            {
                // Not a MidCOM DBA object, skip
                continue;
            }

            $root_classes[] = $schema_type;
        }
        unset($root_exceptions_notroot);
        $root_exceptions_forceroot = midcom_baseclasses_components_configuration::get('midcom.helper.reflector', 'config')->get('root_class_exceptions_forceroot');
        // Safety against misconfiguration
        if (!is_array($root_exceptions_forceroot))
        {
            debug_add("config->get('root_class_exceptions_forceroot') did not return array, invalid configuration ??", MIDCOM_LOG_ERROR);
            $root_exceptions_forceroot = array();
        }
        if (!empty($root_exceptions_forceroot))
        {
            foreach ($root_exceptions_forceroot as $schema_type)
            {
                if (!class_exists($schema_type))
                {
                    // Not a valid class
                    debug_add("Type {$schema_type} has been listed to always be root class, but the class does not exist", MIDCOM_LOG_WARN);
                    continue;
                }
                if (in_array($schema_type, $root_classes))
                {
                    // Already listed
                    continue;
                }
                $root_classes[] = $schema_type;
            }
        }
        usort($root_classes, 'strnatcmp');
        return $root_classes;
    }

    /**
     * Static method to add default ("title" and "name") sorts to a QB instance
     *
     * @param midgard_query_builder $qb reference to QB instance
     * @param string $schema_type valid mgdschema class name
     */
    public static function add_schema_sorts_to_qb(&$qb, $schema_type)
    {
        // Sort by "title" and "name" if available
        $ref = midcom_helper_reflector_tree::get($schema_type);
        $dummy = new $schema_type();
        $title_property = $ref->get_title_property($dummy);
        if (   is_string($title_property)
            && midcom::get('dbfactory')->property_exists($schema_type, $title_property))
        {
            $qb->add_order($title_property);
        }
        $name_property = $ref->get_name_property($dummy);
        if (   is_string($name_property)
            && midcom::get('dbfactory')->property_exists($schema_type, $name_property))
        {
            $qb->add_order($name_property);
        }
        unset($title_property, $name_property, $ref, $dummy);
    }
}
?>
