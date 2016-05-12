<?php
/**
 * Joomla! System plugin for SSL redirection
 *
 * @author      Yireo (info@yireo.com)
 * @copyright   Copyright 2016
 * @license     GNU Public License
 * @link        https://www.yireo.com
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
	 * @var $app JApplicationCms
	 */
	protected $app;

	/**
	 * @var $db JDatabaseDriver
	 */
	protected $db;

	/**
	 * @var $helper SSLRedirectHelper
	 */
	private $helper;

	/**
	 * Event onAfterInitialise
	 */
	public function onAfterInitialise()
	{
		// Get system variables
		$uri = JUri::getInstance();
		$this->loadHelper();

		// Redirect the backend
		if ($this->app->isAdmin() == true && $this->params->get('redirect_admin', 0) == 1)
		{
			if ($this->helper->isSSL() == false)
			{
				$uri->setScheme('https');
				$this->redirect($uri->toString());
			}
		}
	}

	/**
	 * Event onAfterRoute
	 */
	public function onAfterRoute()
	{
		if ($this->allowAnyRedirect() == false)
		{
			return;
		}

		// Add HSTS header if enabled
		$this->addHtstHeader();

		// When SSL is currently disabled
		if ($this->helper->isSSL() == false && $this->params->get('redirect_nonssl', 1) == 1)
		{
			$this->redirectFromNonSslToSsl();

			return;
		}

		if ($this->helper->isSSL() == true && $this->params->get('redirect_ssl', 1) == 1)
		{
			$this->redirectFromSslToNonSsl();

			return;
		}
	}

	/**
	 * Perform a redirect from SSL to non-SSL
	 *
	 * @return bool
	 */
	private function redirectFromSslToNonSsl()
	{
		if ($this->allowRedirectFromSslToNonSsl())
		{
			$uri = JUri::getInstance();
			$uri->setScheme('http');
			$this->helper->addDebug('Redirect to non-SSL: ' . $uri->toString());
			$this->redirect($uri->toString());
		}

		$this->helper->addDebug('Not changing SSL state');
	}

	/**
	 * Perform a redirect from non-SSL to SSL
	 *
	 * @return bool
	 */
	private function redirectFromNonSslToSsl()
	{
		if ($this->allowRedirectFromNonSslToSsl())
		{
			$uri = JUri::getInstance();
			$uri->setScheme('https');
			$this->helper->addDebug('Redirect to SSL: ' . $uri->toString());
			$this->redirect($uri->toString());

			return true;
		}

		$this->helper->addDebug('Not changing non-SSL state');

		return false;
	}

	/**
	 * Method to determine whether redirects are allowed at all
	 *
	 * @return bool
	 */
	private function allowAnyRedirect()
	{
		// Do not rewrite for anything else but the frontend
		if ($this->app->isSite() == false)
		{
			return false;
		}

		if ($this->helper->isCurrentHostExcluded())
		{
			return false;
		}

		if ($this->helper->isPostRequest())
		{
			$this->helper->addDebug('Redirect enabled because of POST data');

			return false;
		}

		if ($this->helper->isAjaxRequest())
		{
			$this->helper->addDebug('Redirect enabled because request seems AJAX');

			return false;
		}

		// Don't do anything if the current path is excluded
		if ($this->matchExcludedMenuItems())
		{
			return false;
		}

		// Don't do anything if the current path is excluded
		if ($this->matchExcludedComponents())
		{
			return false;
		}

		// Don't do anything if the current path is excluded
		if ($this->matchExcludedPages())
		{
			return false;
		}

		return true;
	}

	/**
	 * Determine whether to perform a redirect from non-SSL to SSL
	 *
	 * @return bool
	 */
	private function allowRedirectFromNonSslToSsl()
	{
		if ($this->matchAll())
		{
			if ($this->allowRedirectFromSslToNonSsl() == false)
			{
				$this->helper->addDebug('Redirect enabled for all pages');

				return true;
			}
		}

		if ($this->matchNonSslMenuItems())
		{
			$this->helper->addDebug('Redirect disabled because Menu-Item is matched');

			return false;
		}

		if ($this->matchNonSslArticle())
		{
			$this->helper->addDebug('Redirect disabled because article is matched');

			return false;
		}

		if ($this->matchNonSslComponents())
		{
			$this->helper->addDebug('Redirect disabled because component is matched');

			return false;
		}

		if ($this->matchNonSslPages())
		{
			$this->helper->addDebug('Redirect disabled because current path is matched');

			return false;
		}

		if ($this->matchLoggedInUser())
		{
			$this->helper->addDebug('Redirect enabled because user is logged in');

			return true;
		}

		if ($this->matchSslMenuItems())
		{
			$this->helper->addDebug('Redirect enabled because Menu-Item is matched');

			return true;
		}

		if ($this->matchSslArticle())
		{
			$this->helper->addDebug('Redirect enabled because article is matched');

			return true;
		}

		if ($this->matchSslComponents())
		{
			$this->helper->addDebug('Redirect enabled because component is matched');

			return true;
		}

		if ($this->matchSslPages())
		{
			$this->helper->addDebug('Redirect enabled because current path is matched');

			return true;
		}

		$matchPhp = $this->matchPHP();

		if ($matchPhp !== -1 && $matchPhp == true)
		{
			$this->helper->addDebug('Redirect enabled because of PHP expression');

			return true;
		}

		return false;
	}

	/**
	 * Determine whether to perform a redirect from SSL to non-SSL
	 *
	 * @return bool
	 */
	private function allowRedirectFromSslToNonSsl()
	{
		if ($this->matchLoggedInUser())
		{
			$this->helper->addDebug('Redirect disabled because of user is logged in');

			return false;
		}

		if ($this->matchSslMenuItems())
		{
			$this->helper->addDebug('Redirect disabled because of Menu-Item includes this page');

			return false;
		}

		if ($this->matchSslArticle())
		{
			$this->helper->addDebug('Redirect disabled because article is matched');

			return false;
		}

		if ($this->matchSslComponents())
		{
			$this->helper->addDebug('Redirect disabled because component is matched');

			return false;
		}

		if ($this->matchSslPages())
		{
			$this->helper->addDebug('Redirect disabled because current path is matched');

			return false;
		}

		$matchPhp = $this->matchPHP();

		if ($matchPhp !== -1 && $matchPhp == false)
		{
			$this->helper->addDebug('Redirect disabled because of PHP expression');

			return false;
		}

		if ($this->matchNonSslMenuItems())
		{
			$this->helper->addDebug('Redirect enabled because Menu-Item is matched');

			return true;
		}

		if ($this->matchNonSslArticle())
		{
			$this->helper->addDebug('Redirect enabled because article is matched');

			return true;
		}

		if ($this->matchNonSslComponents())
		{
			$this->helper->addDebug('Redirect enabled because component is matched');

			return true;
		}

		if ($this->matchNonSslPages())
		{
			$this->helper->addDebug('Redirect enabled because current path is matched');

			return true;
		}

		return false;
	}

	/**
	 * Match all pages
	 *
	 * @return bool
	 */
	private function matchAll()
	{
		return (bool) $this->params->get('all', 0);
	}

	/**
	 * Match whether a user is logged in (and its access should be private)
	 *
	 * @return bool
	 */
	private function matchLoggedInUser()
	{
		if ($this->params->get('loggedin', -1) == 1 && JFactory::getUser()->guest == 0)
		{
			return true;
		}

		return false;
	}

	/**
	 * Check whether the current Itemid matches the SSL Menu-Items
	 *
	 * @return bool
	 */
	private function matchSslMenuItems()
	{
		return $this->matchMenuItems($this->getMenuItems('menu_items'));
	}

	/**
	 * Check whether the current Itemid matches the non-SSL Menu-Items
	 *
	 * @return bool
	 */
	private function matchNonSslMenuItems()
	{
		return $this->matchMenuItems($this->getMenuItems('nonssl_menu_items'));
	}

	/**
	 * Match the current article
	 *
	 * @return bool
	 */
	private function matchExcludedMenuItems()
	{
		$menuItems = $this->getMenuItems('exclude_menu_items');

		return $this->matchMenuItems($menuItems);
	}
	
	/**
	 * Match whether the current Itemid is present in an array of Menu Items
	 *
	 * @param array $menuItems
	 *
	 * @return bool
	 */
	private function matchMenuItems($menuItems)
	{
		$Itemid = $this->app->input->getInt('Itemid');

		if (in_array($Itemid, $menuItems))
		{
			return true;
		}

		return false;
	}

	/**
	 * Match whether the current path is excluded
	 *
	 * @return bool
	 */
	private function matchSslPages()
	{
		return $this->matchPages('custom_pages');
	}

	/**
	 * Match whether the current path is excluded
	 *
	 * @return bool
	 */
	private function matchNonSslPages()
	{
		return $this->matchPages('nonssl_custom_pages');
	}

	/**
	 * Check if the current path should be excluded
	 *
	 * @return bool
	 */
	private function matchExcludedPages()
	{
		return $this->matchPages('exclude_pages');
	}

	/**
	 * Match whether the current path is excluded
	 *
	 * @param string $paramName
	 *
	 * @return bool
	 */
	private function matchPages($paramName)
	{
		$custom_pages = $this->helper->textToArray($this->params->get($paramName));
		$current_path = $this->getCurrentPath();

		if (empty($custom_pages))
		{
			return false;
		}

		if (empty($current_path))
		{
			return false;
		}

		return $this->helper->matchesPage($custom_pages, $current_path);
	}

	/**
	 * Match the current article
	 *
	 * @return bool
	 */
	private function matchSslArticle()
	{
		$article_ids = $this->helper->textToArray($this->params->get('articles'));

		return $this->matchArticle($article_ids);
	}

	/**
	 * Match the current article
	 *
	 * @return bool
	 */
	private function matchNonSslArticle()
	{
		$article_ids = $this->helper->textToArray($this->params->get('nonssl_articles'));

		return $this->matchArticle($article_ids);
	}

	/**
	 * Match the current article
	 *
	 * @param array $article_ids
	 *
	 * @return bool
	 */
	private function matchArticle($article_ids)
	{
		if ($this->app->input->getCmd('option') != 'com_content')
		{
			return false;
		}

		if ($this->app->input->getCmd('view') != 'article')
		{
			return false;
		}

		if (empty($article_ids))
		{
			return false;
		}

		if (!in_array($this->app->input->getInt('id'), $article_ids))
		{
			return false;
		}

		return true;
	}

	/**
	 * Check whether the component is matched with SSL components
	 *
	 * @return bool
	 */
	private function matchSslComponents()
	{
		return $this->matchComponents($this->getComponents('components'));
	}

	/**
	 * Check whether the component is matched with non-SSL components
	 *
	 * @return bool
	 */
	private function matchNonSslComponents()
	{
		return $this->matchComponents($this->getComponents('nonssl_components'));
	}

	/**
	 * Match whether the current component is excluded
	 *
	 * @return bool
	 */
	private function matchExcludedComponents()
	{
		return $this->matchComponents($this->getComponents('exclude_components'));
	}

	/**
	 * Match the current component with a list of components
	 *
	 * @param array $components
	 *
	 * @return bool
	 */
	private function matchComponents($components)
	{
		if (in_array('ALL', $components))
		{
			return true;
		}

		if (in_array($this->app->input->getCmd('option'), $components))
		{
			return true;
		}

		return false;
	}

	/**
	 * Run PHP code if configured
	 *
	 * @return bool|mixed
	 */
	private function matchPHP()
	{
		$selection = $this->params->get('custom_php');
		$selection = trim($selection);

		if (empty($selection))
		{
			return -1;
		}

		return $this->passPHP($this, $this->params, $selection, 'include');
	}

	/**
	 * Add the HTST header if enabled
	 */
	private function addHtstHeader()
	{
		// Add HSTS header if enabled
		if ((bool) $this->params->get('all', 0) && (bool) $this->params->get('hsts_header', 0))
		{
			$age = 10886400;
			header('Strict-Transport-Security: max-age=' . $age . '; includeSubDomains; preload');
		}
	}

	/**
	 * Returns the list of configured Menu-Items
	 *
	 * @param string $paramName
	 *
	 * @return array|mixed
	 */
	private function getMenuItems($paramName)
	{
		$menuItems = $this->params->get($paramName);

		if (empty($menuItems))
		{
			return array();
		}

		if (!is_array($menuItems))
		{
			$menuItems = array($menuItems);
		}

		return $menuItems;
	}
	
	/**
	 * Return the list of parameter-based components
	 *
	 * @param string $paramName
	 *
	 * @return array|mixed
	 */
	private function getComponents($paramName)
	{
		$components = $this->params->get($paramName);

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

		return $components;
	}

	/**
	 * Return the current path
	 *
	 * @return mixed
	 */
	private function getCurrentPath()
	{
		$uri = JUri::getInstance();

		return $uri->toString(array('path', 'query', 'fragment'));
	}

	/**
	 * Helper-method to evaluate a string in PHP
	 * (borrowed from NoNumber Advanced Module Manager)
	 *
	 * @access private
	 *
	 * @param $main       object
	 * @param $params     \Joomla\Registry\Registry
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
				$article = $this->getArticleById($main->_params->id);
			}

			if (!isset($Itemid))
			{
				$Itemid = $this->app->input->getInt('Itemid');
			}

			if (!isset($mainframe))
			{
				$mainframe = (strpos($php, '$mainframe') === false) ? '' : $this->app;
			}

			if (!isset($app))
			{
				$app = (strpos($php, '$app') === false) ? '' : $this->app;
			}

			if (!isset($database))
			{
				$database = (strpos($php, '$database') === false) ? '' : $this->db;
			}

			if (!isset($db))
			{
				$db = (strpos($php, '$db') === false) ? '' : $this->db;
			}

			if (!isset($user))
			{
				$user = (strpos($php, '$user') === false) ? '' : JFactory::getUser();
			}

			if (!isset($option))
			{
				$option = (strpos($php, '$option') === false) ? '' : $this->app->input->getCmd('option');
			}

			if (!isset($view))
			{
				$view = (strpos($php, '$view') === false) ? '' : $this->app->input->getCmd('view');
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
	 * Load an article by ID
	 *
	 * @param $id
	 *
	 * @return mixed
	 */
	private function getArticleById($id)
	{
		/** @var $model ContentModelArticle */
		require_once JPATH_SITE . '/components/com_content/models/article.php';
		$model = JModel::getInstance('article', 'contentModel');
		$article = $model->getItem($id);

		return $article;
	}

	/**
	 * Helper method to redirect to a certain URL
	 *
	 * @param $url
	 */
	private function redirect($url)
	{
		$status = $this->params->get('http_status', 301);
		$httpStatus = $this->helper->getHeaderByStatus($status);

		header($httpStatus, true);
		header('Location: ' . $url);
		header('Content-Type: text/html; charset=utf-8');

		$this->app->close();
		exit;
	}

	/**
	 * Get the helper class
	 *
	 * @return SSLRedirectHelper
	 */
	private function loadHelper()
	{
		if (empty($this->helper))
		{
			require_once 'helper.php';
			$this->helper = new SSLRedirectHelper($this->params);
		}

		return $this->helper;
	}
}
