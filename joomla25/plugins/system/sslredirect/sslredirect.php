<?php
/**
 * Joomla! System plugin for SSL redirection
 *
 * @author Yireo (info@yireo.com)
 * @package Joomla!
 * @copyright Copyright 2012
 * @license GNU Public License
 * @link http://www.yireo.com
 * @contributor Jisse Reitsma, Yireo (main code)
 * @contributor Stephen Roberts (custom PHP-addition)
 * @contributor Peter van Westen, NoNumber (copying passPHP function)
 * version 0.9.4:
    New PHP-evaluation
    J30 compat
    Language file
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die( 'Restricted access' );

// Import the parent class
jimport( 'joomla.plugin.plugin' );

/**
 * SSL Redirect System Plugin
 */
class plgSystemSSLRedirect extends JPlugin
{
    /**
     * Load the parameters
     *
     * @access private
     * @param null
     * @return JParameter
     */
    private function getParams()
    {
        jimport('joomla.version');
        $version = new JVersion();
        if (version_compare($version->RELEASE, '1.5', 'eq')) {
            $plugin = JPluginHelper::getPlugin('system', 'sslredirect');
            $params = new JParameter($plugin->params);
            return $params;
        } else {
            return $this->params;
        }
    }

    /**
     * Event onAfterInitialise
     *
     * @access public
     * @param null
     * @return null
     */
    public function onAfterInitialise()
    {
        // Get system variables
        $application = JFactory::getApplication();
        $uri = JFactory::getURI();

        // Redirect the backend
        if ($application->isAdmin() == true && $this->getParams()->get('redirect_admin', 0) == 1) {
            if ($uri->isSSL() == false) {
                $uri->setScheme('https');
                $application->redirect($uri->toString());
                return $application->close();
            }
        }

    }

    /**
     * Event onAfterRoute
     *
     * @access public
     * @param null
     * @return null
     */
    public function onAfterRoute()
    {
        // Get system variables
        $application = JFactory::getApplication();
        $uri = JFactory::getURI();
        $current_path = $uri->toString(array('path', 'query', 'fragment'));
        $Itemid = JRequest::getInt('Itemid');

        // Do not rewrite for anything else but the frontend
        if ($application->isSite() == false) {
            return false;
        }

        // Redirect all pages
        if ($this->getParams()->get('all', 0) == 1 && $uri->isSSL() == false) {
            $uri->setScheme('https');
            $application->redirect($uri->toString());
            return $application->close();
        }

        // Don't do anything if format=raw or tmpl=component
        $format = JRequest::getCmd('format');
        $tmpl = JRequest::getCmd('tmpl');
        if ($format == 'raw' || $tmpl == 'component') {
            return;
        }

        // Get and parse the menu-items from the plugin parameters
        $menu_items = $this->getParams()->get('menu_items');
        if (empty($menu_items)) { 
            $menu_items = array();
        } else if (!is_array($menu_items)) {
            $menu_items = array($menu_items);
        }

        // Get and parse the components from the plugin parameters
        $components = $this->getParams()->get('components');
        if (empty($components)) { 
            $components= array();
        } else if (!is_array($components)) {
            $components = array($components);
        }

        // Get and parse the excluded components from the plugin parameters
        $exclude_components = $this->getParams()->get('exclude_components');
        if (empty($exclude_components)) { 
            $exclude_components= array();
        } else if (!is_array($exclude_components)) {
            $exclude_components = array($exclude_components);
        }

        // Don't do anything if the current component is excluded
        if (in_array(JRequest::getCmd('option'), $exclude_components)) {
            return;
        }

        // Get and parse the custom-pages from the plugin parameters
        $custom_pages = $this->getParams()->get('custom_pages');
        if (empty($custom_pages)) {
            $custom_pages = array();
        } else {
            $tmp = explode("\n", $custom_pages);
            $custom_pages = array();
            foreach ($tmp as $index => $text) {
                if (!empty($text)) {
                    $custom_pages[$index] = trim($text);
                }
            }
        }

        // Evaluate custom PHP
        $selection = $this->getParams()->get('custom_php');
        if(!empty($selection)) {
            $passPHP = $this->passPHP($this, $this, $selection, 'include');
        } else {
            $passPHP = false;
        }

        // When SSL is currently disabled
        if ($uri->isSSL() == false && $this->getParams()->get('redirect_nonssl', 1) == 1) {

            $redirect = false;

            // Do not redirect if this is POST-request 
            $post = JRequest::get('post');
            if (is_array($post) && !empty($post)) {
                $redirect = false;

            // Do not redirect with other API-calls
            } else if (in_array(JRequest::getCmd('view'), array('jsonrpc', 'ajax', 'api'))) {
                $redirect = false;
            } else if (in_array(JRequest::getCmd('controller'), array('jsonrpc', 'ajax', 'api'))) {
                $redirect = false;

            // Determine whether to do a redirect based on whether an user is logged in
            } else if ($this->getParams()->get('loggedin', -1) == 1 && JFactory::getUser()->guest == 0) { 
                $redirect = true;

            // Determine whether to do a redirect based on the menu-items
            } else if (in_array($Itemid, $menu_items)) {
                $redirect = true;

            // Determine whether to do a redirect based on the component
            } else if (in_array(JRequest::getCmd('option'), $components)) {
                $redirect = true;

            // Determine whether to do a redirect based on the custom-pages
            } else if (!empty($custom_pages) && !empty($current_path)) {
                foreach ($custom_pages as $custom_page) {
                    $pos = strpos($current_path, $custom_page);
                    if ($pos !== false && ($pos == 0 || $pos == 1)) {
                        $redirect = true;
                        break;
                    }
                }

            // Determine whether to do a redirect based on custom PHP
            } else if ($passPHP == true) {
                $redirect = true;
            }
            

            // Redirect to SSL
            if ($redirect == true) {
                $uri->setScheme('https');
                $application->redirect($uri->toString());
            }

        // When SSL is currently enabled
        } else if ($uri->isSSL() == true && $this->getParams()->get('redirect_ssl', 1) == 1) {

            // Determine whether to do a redirect
            $redirect = true;

            // Do not redirect if this is POST-request 
            $post = JRequest::get('post');
            if (is_array($post) && !empty($post)) {
                $redirect = false;

            // Do not redirect with other API-calls
            } else if (in_array(JRequest::getCmd('controller'), array('jsonrpc', 'ajax', 'api'))) {
                $redirect = false;
            } else if (in_array(JRequest::getCmd('view'), array('jsonrpc', 'ajax', 'api'))) {
                $redirect = false;

            // Determine whether to do a redirect based on whether an user is logged in
            } else if ($this->getParams()->get('loggedin', -1) == 0 && JFactory::getUser()->guest == 0) { 
                $redirect = true;

            // Determine whether to do a redirect based on the menu-items
            } else if (in_array($Itemid, $menu_items)) {
                $redirect = false;

            // Determine whether to do a redirect based on the component
            } else if (in_array(JRequest::getCmd('option'), $components)) {
                $redirect = false;

            // Determine whether to do a redirect based on the custom-pages
            } else if (!empty($custom_pages) && !empty($current_path)) {
                foreach ($custom_pages as $custom_page) {
                    $pos = strpos($current_path, $custom_page);
                    if ($pos !== false && ($pos == 0 || $pos == 1)) {
                        $redirect = false;
                        break;
                    }
                }
            
            // Determine whether to do a redirect based on custom PHP
            } else if ($passPHP == true) {
                $redirect = false;
            }

            // Redirect to non-SSL
            if ($redirect) {
                $uri->setScheme('http');
                $application->redirect($uri->toString());
                return $application->close();
            }
        }
    }

    /**
     * Helper-method to evaluate a string in PHP
     * (borrowed from NoNumber Advanced Module Manager)
     *
     * @access private
     * @param $main object
     * @param $params JParameter 
     * @param $selection array
     * @param $assignment string
     * @param $article int
     * @return boolean
     */
    private function passPHP(&$main, &$params, $selection = array(), $assignment = 'all', $article = 0)
    {
        if (!is_array($selection)) {
            $selection = array($selection);
        }

        $pass = 0;
        foreach ($selection as $php) {
            // replace \n with newline and other fix stuff
            $php = str_replace('\|', '|', $php);
            $php = preg_replace('#(?<!\\\)\\\n#', "\n", $php);
            $php = str_replace('[:REGEX_ENTER:]', '\n', $php);

            if (trim($php) == '') {
                $pass = 1;
                break;
            }

            if (!$article && !(strpos($php, '$article') === false) && $main->_params->option == 'com_content' && $main->_params->view == 'article') {
                require_once JPATH_SITE.'/components/com_content/models/article.php';
                $model = JModel::getInstance('article', 'contentModel');
                $article = $model->getItem($main->_params->id);
            }
            if (!isset($Itemid)) {
                $Itemid = JRequest::getInt('Itemid');
            }
            if (!isset($mainframe)) {
                $mainframe = (strpos($php, '$mainframe') === false) ? '' : JFactory::getApplication();
            }
            if (!isset($app)) {
                $app = (strpos($php, '$app') === false) ? '' : JFactory::getApplication();
            }
            if (!isset($database)) {
                $database = (strpos($php, '$database') === false) ? '' : JFactory::getDBO();
            }
            if (!isset($db)) {
                $db = (strpos($php, '$db') === false) ? '' : JFactory::getDBO();
            }
            if (!isset($user)) {
                $user = (strpos($php, '$user') === false) ? '' : JFactory::getUser();
            }
            if (!isset($option)) {
                $option = (strpos($php, '$option') === false) ? '' : JRequest::getCmd('option');
            }
            if (!isset($view)) {
                $view = (strpos($php, '$view') === false) ? '' : JRequest::getCmd('view');
            }

            $vars = '$article,$Itemid,$mainframe,$app,$database,$db,$user';

            $val = '$temp_PHP_Val = create_function( \''.$vars.'\', $php.\';\' );';
            $val .= ' $pass = ( $temp_PHP_Val('.$vars.') ) ? 1 : 0; unset( $temp_PHP_Val );';
            @eval($val);

            if ($pass) {
                break;
            }
        }

        if ($pass) {
            return ($assignment == 'include');
        } else {
            return ($assignment == 'exclude');
        }
    }
}
