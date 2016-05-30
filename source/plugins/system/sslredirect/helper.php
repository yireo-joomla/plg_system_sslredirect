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

/**
 * SSL Redirect helper
 */
class SSLRedirectHelper
{
	/**
	 * @var \Joomla\Registry\Registry
	 */
	protected $params;

	/**
	 * @var JApplicationCms
	 */
	protected $app;

	/**
	 * @param $params
	 */
	public function __construct($params)
	{
		$this->app    = JFactory::getApplication();
		$this->params = $params;
	}

	/**
	 * Debug helper
	 *
	 * @param $msg string
	 * @param $die bool
	 *
	 * @return bool
	 */
	public function addDebug($msg)
	{
		if ((bool) $this->params->get('debug') == false)
		{
			return false;
		}

		header('X-SSL-Redirect: ' . $msg, false);
	}

	/**
	 * Helper method to check whether a path matches a set of pages
	 *
	 * @param $pages
	 * @param $current_path
	 *
	 * @return bool
	 */
	public function matchesPage($pages, $current_path)
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
	 * Method to convert a string into array
	 *
	 * @param $text string
	 *
	 * @return array
	 */
	public function textToArray($text)
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
	 * Return the HTTP header-text by HTTP status-code
	 *
	 * @param $status
	 *
	 * @return string
	 */
	public function getHeaderByStatus($status)
	{
		if ($status == 301)
		{
			return 'HTTP/1.1 301 Moved Permanently';
		}

		if ($status == 302)
		{
			return 'HTTP/1.1 302 Found';
		}

		if ($status == 303)
		{
			return 'HTTP/1.1 303 See other';
		}

		if ($status == 307)
		{
			return 'HTTP/1.1 307 Temporary Redirect';
		}

		return 'HTTP/1.1 303 See other';
	}

	/**
	 * Helper-method to check whether SSL is active or not
	 *
	 * @return bool
	 */
	public function isSSL()
	{
		// Support for proxy headers
		if (isset($_SERVER['X-FORWARDED-PROTO']))
		{
			if ($_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
			{
				return true;
			}

			return false;
		}

		$uri = JFactory::getURI();

		return (bool) $uri->isSSL();
	}

	/**
	 * Determine whether the current request is a POST request
	 *
	 * @return bool
	 */
	public function isPostRequest()
	{
		if ($this->isJoomla25())
		{
			$post = JRequest::get('post');
		}
		else
		{
			$post = $this->app->input->post->getArray();
		}

		if (is_array($post) && !empty($post))
		{
			return true;
		}

		return false;
	}

	/**
	 * Determine whether the current request is an AJAX request
	 *
	 * @return bool
	 */
	public function isAjaxRequest()
	{
		$format = $this->app->input->getCmd('format');
		$tmpl   = $this->app->input->getCmd('tmpl');

		if ($format == 'raw' || $tmpl == 'component')
		{
			return true;
		}

		if (in_array($this->app->input->getCmd('view'), array('jsonrpc', 'ajax', 'api')))
		{
			return true;
		}

		if (in_array($this->app->input->getCmd('controller'), array('jsonrpc', 'ajax', 'api')))
		{
			return true;
		}

		return false;
	}

	/**
	 * Determine whether the current host is excluded
	 *
	 * @return bool
	 */
	public function isCurrentHostExcluded()
	{
		$exclude_hosts = $this->params->get('exclude_hosts');
		$exclude_hosts = $this->textToArray($exclude_hosts);
		$current_host  = $_SERVER['HTTP_HOST'];

		if (!empty($exclude_hosts) && in_array($current_host, $exclude_hosts))
		{
			return true;
		}

		return false;
	}

	/**
	 * Load an article by ID
	 *
	 * @param $id
	 *
	 * @return mixed
	 */
	public function getArticleById($id)
	{
		/** @var $model ContentModelArticle */
		require_once JPATH_SITE . '/components/com_content/models/article.php';
		$model   = JModel::getInstance('article', 'contentModel');
		$article = $model->getItem($id);

		return $article;
	}

	/**
	 * Check for Joomla 2.5 support
	 *
	 * @return bool
	 */
	public function isJoomla25()
	{
		JLoader::import('joomla.version');
		$jversion = new JVersion;
		$version  = $jversion->RELEASE;

		if (version_compare($version, '2.5', 'eq'))
		{
			return true;
		}

		return false;
	}
}
