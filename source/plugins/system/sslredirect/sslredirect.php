<?php
/**
 * Joomla! System plugin for SSL redirection
 *
 * @author      Yireo (info@yireo.com)
 * @package     Joomla!
 * @copyright   Copyright 2015
 * @license     GNU Public License
 * @link        http://www.yireo.com
 * @contributor Jisse Reitsma, Yireo (main code)
 * @contributor Stephen Roberts (custom PHP-addition)
 * @contributor Peter van Westen, NoNumber (copying passPHP function)
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

// Import the parent class
jimport('joomla.plugin.plugin');

/**
 * SSL Redirect System Plugin
 */
class PlgSystemSSLRedirect extends JPlugin
{
	/**
	 * Event onAfterInitialise
	 *
	 * @access public
	 *
	 * @param null
	 *
	 * @return null
	 */
	public function onAfterInitialise()
	{
		// Get system variables
		$application = JFactory::getApplication();
		$uri = JFactory::getURI();

		// Redirect the backend
		if ($application->isAdmin() == true && $this->params->get('redirect_admin', 0) == 1)
		{
			if ($this->isSSL() == false)
			{
				$uri->setScheme('https');
				$this->redirect($uri->toString());
			}
		}

	}

	/**
	 * Event onAfterRoute
	 *
	 * @access public
	 *
	 * @param null
	 *
	 * @return null
	 */
	public function onAfterRoute()
	{
		// Get system variables
		$application = JFactory::getApplication();
		$uri = JFactory::getURI();
		$current_path = $uri->toString(array('path', 'query', 'fragment'));
		$Itemid = $application->input->getInt('Itemid');

		// Do not rewrite for anything else but the frontend
		if ($application->isSite() == false)
		{
			return false;
		}

		// Add HSTS header if enabled
		$this->addHtstHeader();

		// Redirect all pages
		if ($this->params->get('all', 0) == 1 && $this->isSSL() == false)
		{
			$uri->setScheme('https');
			$this->redirect($uri->toString());
		}

		// Don't do anything if format=raw or tmpl=component
		$format = $application->input->getCmd('format');
		$tmpl = $application->input->getCmd('tmpl');

		if ($format == 'raw' || $tmpl == 'component')
		{
			return false;
		}

		// Get and parse the menu-items from the plugin parameters
		$menu_items = $this->params->get('menu_items');

		if (empty($menu_items))
		{
			$menu_items = array();
		}
		else
		{
			if (!is_array($menu_items))
			{
				$menu_items = array($menu_items);
			}
		}

		// Get and parse the components from the plugin parameters
		$components = $this->params->get('components');

		if (empty($components))
		{
			$components = array();
		}
		else
		{
			if (!is_array($components))
			{
				$components = array($components);
			}
		}

		// Get and parse the excluded components from the plugin parameters
		$exclude_components = $this->params->get('exclude_components');

		if (empty($exclude_components))
		{
			$exclude_components = array();
		}
		else
		{
			if (!is_array($exclude_components))
			{
				$exclude_components = array($exclude_components);
			}
		}

		// Don't do anything if the current component is excluded
		if (in_array($application->input->getCmd('option'), $exclude_components))
		{
			return false;
		}

		// Get and parse the exclude-pages from the plugin parameters
		$exclude_pages = $this->params->get('exclude_pages');
		$exclude_pages = $this->textToArray($exclude_pages);

		// Don't do anything if the current component is excluded
		if (!empty($exclude_pages))
		{
			foreach ($exclude_pages as $exclude)
			{
				if (stristr($current_path, $exclude))
				{
					return false;
				}
			}
		}

		// Get and parse the custom-pages from the plugin parameters
		$custom_pages = $this->textToArray($this->params->get('custom_pages'));

		// Get and parse the custom-pages from the plugin parameters
		$article_ids = $this->textToArray($this->params->get('articles'));

		// Evaluate custom PHP
		$selection = $this->params->get('custom_php');

		if (!empty($selection))
		{
			$passPHP = $this->passPHP($this, $this, $selection, 'include');
		}
		else
		{
			$passPHP = false;
		}

		// When SSL is currently disabled
		if ($this->isSSL() == false && $this->params->get('redirect_nonssl', 1) == 1)
		{
			$redirect = false;

			// Do not redirect if this is POST-request
			$post = $application->input->post->getArray();

			if (is_array($post) && !empty($post))
			{
				$this->addDebug('Redirect enabled because of POST data');
				$redirect = false;

				// Do not redirect with other API-calls
			}
			elseif (in_array($application->input->getCmd('view'), array('jsonrpc', 'ajax', 'api')))
			{
				$this->addDebug('Redirect enabled because of API controller');
				$redirect = false;

			}
			elseif (in_array($application->input->getCmd('controller'), array('jsonrpc', 'ajax', 'api')))
			{
				$this->addDebug('Redirect enabled because of API view');
				$redirect = false;

				// Determine whether to do a redirect based on whether an user is logged in
			}
			elseif ($this->params->get('loggedin', -1) == 1 && JFactory::getUser()->guest == 0)
			{
				$this->addDebug('Redirect enabled because user is logged in');
				$redirect = true;

				// Determine whether to do a redirect based on the menu-items
			}
			elseif (in_array($Itemid, $menu_items))
			{
				$this->addDebug('Redirect enabled because Menu-Item included');
				$redirect = true;

				// Determine whether to do a redirect based on the menu-items
			}
			else
			{
				if ($application->input->getCmd('option') == 'com_content' && $application->input->getCmd('view') == 'article'
					&& !empty($article_ids) && in_array($application->input->getInt('id'), $article_ids))
				{
					$this->addDebug('Redirect enabled because article included');
					$redirect = true;

					// Determine whether to do a redirect based on the component
				}
				else
				{
					if (in_array('ALL', $components))
					{
						$this->addDebug('Redirect enabled because component is ALL');
						$redirect = true;

						// Determine whether to do a redirect based on the component
					}
					else
					{
						if (in_array($application->input->getCmd('option'), $components))
						{
							$this->addDebug('Redirect enabled because component include');
							$redirect = true;

							// Determine whether to do a redirect based on the custom-pages
						}
						else
						{
							if (!empty($custom_pages) && !empty($current_path) && $this->matchesPage($custom_pages, $current_path))
							{
								$this->addDebug('Redirect enabled because component include');
								$redirect = true;
							}
							else
							{
								if ($passPHP == true)
								{
									$this->addDebug('Redirect enabled because of PHP expression');
									$redirect = true;
								}
							}
						}
					}
				}
			}

			// Redirect to SSL
			if ($redirect == true)
			{
				$this->addDebug('Redirect to SSL', true);
				$uri->setScheme('https');
				$this->redirect($uri->toString());
			}

			$this->addDebug('Not changing non-SSL state', true);

			// When SSL is currently enabled
		}
		elseif ($this->isSSL() == true && $this->params->get('redirect_ssl', 1) == 1)
		{
			// Determine whether to do a redirect
			$redirect = true;

			// Do not redirect if this is POST-request
			$post = $application->input->post->getArray();

			if (is_array($post) && !empty($post))
			{
				$this->addDebug('Redirect disabled because of POST data');
				$redirect = false;

				// Do not redirect with other API-calls
			}
			elseif (in_array($application->input->getCmd('controller'), array('jsonrpc', 'ajax', 'api')))
			{
				$this->addDebug('Redirect disabled because of API controller');
				$redirect = false;

			}
			elseif (in_array($application->input->getCmd('view'), array('jsonrpc', 'ajax', 'api')))
			{
				$this->addDebug('Redirect disabled because of API view');
				$redirect = false;

				// Determine whether to do a redirect based on whether an user is logged in
			}
			elseif ($this->params->get('loggedin', -1) == 1 && JFactory::getUser()->guest == 0)
			{
				$this->addDebug('Redirect disabled because of user is logged in');
				$redirect = false;

				// Determine whether to do a redirect based on the menu-items
			}
			elseif (in_array($Itemid, $menu_items))
			{
				$this->addDebug('Redirect disabled because of Menu-Item includes this page');
				$redirect = false;

				// Determine whether to do a redirect based on the menu-items
			}
			elseif ($application->input->getCmd('option') == 'com_content' && $application->input->getCmd('view') == 'article'
				&& !empty($article_ids) && in_array($application->input->getInt('id'), $article_ids)
			)
			{
				$this->addDebug('Redirect disabled because of article-list includes this page');
				$redirect = false;

				// Determine whether to do a redirect based on the component
			}
			elseif (in_array('ALL', $components))
			{
				$this->addDebug('Redirect disabled because of component is set to ALL');
				$redirect = false;

				// Determine whether to do a redirect based on the component
			}
			elseif (in_array($application->input->getCmd('option'), $components))
			{
				$this->addDebug('Redirect disabled because of component-list includes this page');
				$redirect = false;

				// Determine whether to do a redirect based on the custom-pages
			}
			elseif (!empty($custom_pages) && !empty($current_path) && $this->matchesPage($custom_pages, $current_path))
			{
				$this->addDebug('Redirect disabled because of custom page');
				$redirect = false;
			}
			elseif ($passPHP == true)
			{
				$this->addDebug('Redirect disabled because of PHP expression');
				$redirect = false;
			}

			// Redirect to non-SSL
			if ($redirect)
			{
				$this->addDebug('Redirect to non-SSL', true);
				$uri->setScheme('http');
				$this->redirect($uri->toString());
			}

			$this->addDebug('Not changing SSL state', true);
		}
	}

	/**
	 * Helper method to check whether a path matches a set of pages
	 *
	 * @param $pages
	 * @param $current_path
	 *
	 * @return bool
	 */
	private function matchesPage($pages, $current_path)
	{
		foreach ($pages as $page)
		{
			$pos = strpos($current_path, $page);

			if ($pos !== false && ($pos == 0 || $pos == 1))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Add the HTST header if enabled
	 */
	private function addHtstHeader()
	{
		// Add HSTS header if enabled
		if ($this->params->get('all', 0) == 1 && $this->params->get('hsts_header', 0) == 1)
		{
			$age = 10886400;
			header('Strict-Transport-Security: max-age=' . $age . '; includeSubDomains; preload');
		}
	}

	/**
	 * Helper-method to evaluate a string in PHP
	 * (borrowed from NoNumber Advanced Module Manager)
	 *
	 * @access private
	 *
	 * @param $main       object
	 * @param $params     JParameter
	 * @param $selection  array
	 * @param $assignment string
	 * @param $article    int
	 *
	 * @return boolean
	 */
	private function passPHP(&$main, &$params, $selection = array(), $assignment = 'all', $article = 0)
	{
		if (!is_array($selection))
		{
			$selection = array($selection);
		}

		$pass = 0;
		$app = JFactory::getApplication();

		foreach ($selection as $php)
		{
			// Replace \n with newline and other fix stuff
			$php = str_replace('\|', '|', $php);
			$php = preg_replace('#(?<!\\\)\\\n#', "\n", $php);
			$php = str_replace('[:REGEX_ENTER:]', '\n', $php);

			if (trim($php) == '')
			{
				$pass = 1;
				break;
			}

			if (!$article && !(strpos($php, '$article') === false) && $main->_params->option == 'com_content' && $main->_params->view == 'article')
			{
				require_once JPATH_SITE . '/components/com_content/models/article.php';
				$model = JModel::getInstance('article', 'contentModel');
				$article = $model->getItem($main->_params->id);
			}

			if (!isset($Itemid))
			{
				$Itemid = $app->input->getInt('Itemid');
			}

			if (!isset($mainframe))
			{
				$mainframe = (strpos($php, '$mainframe') === false) ? '' : JFactory::getApplication();
			}

			if (!isset($app))
			{
				$app = (strpos($php, '$app') === false) ? '' : JFactory::getApplication();
			}

			if (!isset($database))
			{
				$database = (strpos($php, '$database') === false) ? '' : JFactory::getDBO();
			}

			if (!isset($db))
			{
				$db = (strpos($php, '$db') === false) ? '' : JFactory::getDBO();
			}

			if (!isset($user))
			{
				$user = (strpos($php, '$user') === false) ? '' : JFactory::getUser();
			}

			if (!isset($option))
			{
				$option = (strpos($php, '$option') === false) ? '' : $app->input->getCmd('option');
			}

			if (!isset($view))
			{
				$view = (strpos($php, '$view') === false) ? '' : $app->input->getCmd('view');
			}

			$vars = '$article,$Itemid,$mainframe,$app,$database,$db,$user';

			$val = '$temp_PHP_Val = create_function( \'' . $vars . '\', $php.\';\' );';
			$val .= ' $pass = ( $temp_PHP_Val(' . $vars . ') ) ? 1 : 0; unset( $temp_PHP_Val );';
			@eval($val);

			if ($pass)
			{
				break;
			}
		}

		if ($pass)
		{
			return ($assignment == 'include');
		}

		return ($assignment == 'exclude');
	}

	/**
	 * Helper-method to convert a string into array
	 *
	 * @access private
	 *
	 * @param $text string
	 *
	 * @return array
	 */
	private function textToArray($text)
	{
		if (empty($text))
		{
			return array();
		}

		if (strstr($text, ','))
		{
			$tmp = explode(",", $text);
		}
		else
		{
			$tmp = explode("\n", $text);
		}

		$return = array();

		foreach ($tmp as $index => $text)
		{
			$text = trim($text);

			if (!empty($text) && !in_array($text, array(',')))
			{
				$return[$index] = $text;
			}
		}

		return $return;
	}

	/**
	 * Helper-method to convert a string into array
	 *
	 * @access private
	 *
	 * @param $text string
	 *
	 * @return array
	 */
	private function isSSL()
	{
		// Support for proxy headers
		if (isset($_SERVER['X-FORWARDED-PROTO']))
		{
			if ($_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
			{
				return true;
			}
			else
			{
				return false;
			}
		}

		$uri = JFactory::getURI();

		return $uri->isSSL();
	}

	/**
	 * Debug helper
	 *
	 * @param      $msg
	 * @param bool $die
	 *
	 * @return bool
	 */
	private function addDebug($msg, $die = false)
	{
		if ($_SERVER['REMOTE_ADDR'] != 'tmp')
		{
			return false;
		}

		echo $msg . "<br/>\n";

		if ($die == true)
		{
			die();
		}
	}

	/**
	 * Helper method to redirect to a certain URL
	 *
	 * @param $url
	 */
	private function redirect($url)
	{
		$status = $this->params->get('http_status', 301);

		if ($status == 301)
		{
			$httpStatus = 'HTTP/1.1 301 Moved Permanently';
		}
		elseif ($status == 302)
		{
			$httpStatus = 'HTTP/1.1 302 Found';
		}
		elseif ($status == 303)
		{
			$httpStatus = 'HTTP/1.1 303 See other';
		}
		elseif ($status == 307)
		{
			$httpStatus = 'HTTP/1.1 307 Temporary Redirect';
		}
		else
		{
			$httpStatus = 'HTTP/1.1 303 See other';
		}

		header($httpStatus, true);
		header('Location: ' . $url);
		header('Content-Type: text/html; charset=utf-8');
		
        $application = JFactory::getApplication();
		$application->close();
		exit;
	}
}
