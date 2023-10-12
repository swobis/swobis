<?php
/**
 * Plugin Name: SWOBIS
 * Plugin URI: https://wordpress.org/plugins/swobis
 * Description: Implementation of a mechanism for flexible exchange of various data between SBIS and a site running WordPress.
 * Version: 0.1.0
 * Requires at least: 5.2
 * Requires PHP: 7.0
 * Text Domain: swobis
 * Domain Path: /assets/languages
 * Copyright: SWOBIS team © 2020-2023
 * Author: SWOBIS team
 * Author URI: https://swobis.ru
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package WordPress\Plugins
 **/
namespace
{
	defined('ABSPATH') || exit;

	if(version_compare(PHP_VERSION, '7.0') < 0)
	{
		return false;
	}

	if(false === defined('SWOBIS_PLUGIN_FILE'))
	{
		define('SWOBIS_PLUGIN_FILE', __FILE__);

		$autoloader = __DIR__ . '/vendor/autoload.php';

		if(!is_readable($autoloader))
		{
			trigger_error('File is not found: ' . $autoloader);
			return false;
		}

		require_once $autoloader;

		/**
		 * For external use
		 *
		 * @return Swobis\Core Main instance of core
		 */
		function swobis(): Swobis\Core
		{
			return Swobis\Core();
		}
	}
}

/**
 * @package Swobis
 */
namespace Swobis
{
	/**
	 * For internal use
	 *
	 * @return Core Main instance of plugin core
	 */
	function core(): Core
	{
		return Core::instance();
	}

	$loader = new \Digiom\Woplucore\Loader();

	try
	{
		$loader->addNamespace(__NAMESPACE__, plugin_dir_path(__FILE__) . 'src');

		$loader->register(__FILE__);

		$loader->registerActivation([Activation::class, 'instance']);
		$loader->registerDeactivation([Deactivation::class, 'instance']);
		$loader->registerUninstall([Uninstall::class, 'instance']);
	}
	catch(\Throwable $e)
	{
		trigger_error($e->getMessage());
		return false;
	}

	$context = new Context(__FILE__, 'swobis', $loader);

	core()->register($context);
}