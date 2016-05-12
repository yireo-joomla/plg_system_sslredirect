<?php
/**
 * Joomla! System plugin for SSL redirection
 *
 * @author    Yireo (info@yireo.com)
 * @package   Joomla!
 * @copyright Copyright 2016
 * @license   GNU Public License
 * @link      https://www.yireo.com
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

// Import classes
jimport('joomla.html.html');
jimport('joomla.access.access');
jimport('joomla.form.formfield');

include_once JPATH_LIBRARIES . '/joomla/form/fields/list.php';

/**
 * Form Field-class for selecting multiple menu-items
 */
class YireoFormFieldMenuItems extends JFormFieldList
{
	public $type = 'MenuItems';

	/*
	 * Method to construct the HTML of this element
	 *
	 * @return string
	 */
	protected function getInput()
	{
		$name  = $this->name . '[]';
		$value = $this->value;
		$db    = JFactory::getDbo();

		// Load the list of components
		$query = 'SELECT * FROM `#__menu` WHERE `client_id`="0"';
		$db->setQuery($query);
		$menuitems = $db->loadObjectList();

		$options = array();

		$options = array_merge(parent::getOptions(), $options);

		foreach ($menuitems as $menuitem)
		{
			$options[] = JHTML::_('select.option', $menuitem->id, $menuitem->title . ' [' . $menuitem->id . ']', 'value', 'text');
		}

		$size    = (count($options) > 12) ? 12 : count($options);
		$attribs = 'class="inputbox" multiple="multiple" size="' . $size . '"';

		return JHTML::_('select.genericlist', $options, $name, $attribs, 'value', 'text', $value, $name);
	}
}
