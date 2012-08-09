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
 * Renders a menu items element
 */
class JElementMenuItems extends JElement
{
	public $_name = 'MenuItems';

	public function fetchElement($name, $value, &$node, $control_name)
	{
		$db =& JFactory::getDBO();

		$menuType = $this->_parent->get('menu_type');
		if (!empty($menuType)) {
			$where = ' WHERE menutype = '.$db->Quote($menuType);
		} else {
			$where = ' WHERE 1';
		}

		// load the list of menu types
		$query = 'SELECT menutype, title' .
				' FROM #__menu_types' .
				' ORDER BY title';
		$db->setQuery( $query );
		$menuTypes = $db->loadObjectList();

		if ($state = $node->attributes('state')) {
			$where .= ' AND published = '.(int) $state;
		}

		// load the list of menu items
		$query = 'SELECT id, parent, name, menutype, type' .
				' FROM #__menu' .
				$where .
				' ORDER BY menutype, parent, ordering'
				;

		$db->setQuery($query);
		$menuItems = $db->loadObjectList();

		// establish the hierarchy of the menu
		$children = array();

		if ($menuItems)
		{
			// first pass - collect children
			foreach ($menuItems as $v)
			{
				$pt 	= $v->parent;
				$list 	= @$children[$pt] ? $children[$pt] : array();
				array_push( $list, $v );
				$children[$pt] = $list;
			}
		}

		// second pass - get an indent list of the items
		$list = JHTML::_('menu.treerecurse', 0, '', array(), $children, 9999, 0, 0 );

		// assemble into menutype groups
		$n = count( $list );
		$groupedList = array();
		foreach ($list as $k => $v) {
			$groupedList[$v->menutype][] = &$list[$k];
		}

		// assemble menu items to the array
		$options 	= array();

		foreach ($menuTypes as $type)
		{
			if ($menuType == '')
			{
				$options[]	= JHTML::_('select.option',  '0', '&nbsp;', 'value', 'text', true);
				$options[]	= JHTML::_('select.option',  $type->menutype, $type->title . ' - ' . JText::_( 'Top' ), 'value', 'text', true );
			}
			if (isset( $groupedList[$type->menutype] ))
			{
				$n = count( $groupedList[$type->menutype] );
				for ($i = 0; $i < $n; $i++)
				{
					$item = &$groupedList[$type->menutype][$i];
					
					//If menutype is changed but item is not saved yet, use the new type in the list
					if ( JRequest::getString('option', '', 'get') == 'com_menus' ) {
						$currentItemArray = JRequest::getVar('cid', array(0), '', 'array');
						$currentItemId = (int) $currentItemArray[0];
						$currentItemType = JRequest::getString('type', $item->type, 'get');
						if ( $currentItemId == $item->id && $currentItemType != $item->type) {
							$item->type = $currentItemType;
						}
					}
					
					$disable = strpos($node->attributes('disable'), $item->type) !== false ? true : false;
					$options[] = JHTML::_('select.option',  $item->id, '&nbsp;&nbsp;&nbsp;' .$item->treename, 'value', 'text', $disable );

				}
			}
		}

        $attribs = 'class="inputbox"';
        $attribs .= ' multiple="multiple"';

		return JHTML::_('select.genericlist',  $options, ''.$control_name.'['.$name.'][]', $attribs, 'value', 'text', $value, $control_name.$name);
	}
}
