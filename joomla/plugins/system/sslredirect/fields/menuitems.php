<?php
/**
 * Joomla! 1.6 System plugin for SSL redirection
 *
 * @author Yireo (info@yireo.com)
 * @package Joomla!
 * @copyright Copyright 2011
 * @license GNU Public License
 * @link http://www.yireo.com
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die( 'Restricted access' );

// Import classes
jimport('joomla.html.html');
jimport('joomla.access.access');
jimport('joomla.form.formfield');

/**
 * Form Field-class for selecting multiple menu-items
 */
class JFormFieldMenuItems extends JFormField
{
    public $type = 'MenuItems';

    /*
     * Method to construct the HTML of this element
     *
     * @param null
     * @return string
     */
    protected function getInput()
    {
        $name = $this->name.'[]';
        $value = $this->value;
        $db =& JFactory::getDBO();

        // load the list of components
        $query = 'SELECT * FROM `#__menu` WHERE `client_id`="0"';
        $db->setQuery( $query );
        $menuitems = $db->loadObjectList();

        $options = array();
        foreach ($menuitems as $menuitem) {
            $options[] = JHTML::_('select.option',  $menuitem->id, $menuitem->title.' ['.$menuitem->id.']', 'value', 'text');
        }

        $attribs = 'class="inputbox" multiple="multiple"';
        return JHTML::_('select.genericlist',  $options, $name, $attribs, 'value', 'text', $value, $name);
    }
}
