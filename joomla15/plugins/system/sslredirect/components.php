<?php
/**
 * Joomla! System plugin for SSL redirection
 *
 * @author Yireo (info@yireo.com)
 * @copyright Copyright 2011 Yireo.com. All rights reserved
 * @license GNU Public License
 * @link http://www.yireo.com
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die( 'Restricted access' );

/**
 * Renders a components element
 */
class JElementComponents extends JElement
{
    public $_name = 'Components';

    public function fetchElement($name, $value, &$node, $control_name)
    {
        $db =& JFactory::getDBO();

        // load the list of components
        $query = 'SELECT * FROM `#__components` WHERE `enabled`=1 AND `parent`=0 AND `link`!=""';
        $db->setQuery( $query );
        $components = $db->loadObjectList();

        $options = array();
        foreach ($components as $component) {
            $options[] = JHTML::_('select.option',  $component->option, $component->name.' ['.$component->option.']', 'value', 'text');
        }

        $attribs = 'class="inputbox" multiple="multiple"';
        return JHTML::_('select.genericlist',  $options, ''.$control_name.'['.$name.'][]', $attribs, 'value', 'text', $value, $control_name.$name);
    }
}
