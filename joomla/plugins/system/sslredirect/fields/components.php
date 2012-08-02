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
 * Form Field-class for selecting a component
 */
class JFormFieldComponents extends JFormField
{
    /*
     * Form field type
     */
    public $type = 'Components';

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
        $query = 'SELECT * FROM `#__extensions` WHERE `type`="component" AND `enabled`=1';
        $db->setQuery( $query );
        $components = $db->loadObjectList();

        $options = array();
        foreach ($components as $component) {
            $options[] = JHTML::_('select.option',  $component->element, $component->name.' ['.$component->element.']', 'value', 'text');
        }

        $attribs = 'class="inputbox" multiple="multiple"';
        return JHTML::_('select.genericlist',  $options, $name, $attribs, 'value', 'text', $value, $name);
    }
}
