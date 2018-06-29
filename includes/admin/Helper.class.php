<?php
namespace includes\admin;

require_once('/www/libs/Smarty.class.php');

/**
 * Various helper methods for admin
 */
class Helper
{
	/**
	  * Get smarty instance with basic setup
	  * @param array<string name, string path> $templateDirs
	  * @param string $tempDir Path to tmp dir, will be prepended to $user/smarty/compile_dir
	  * @param string $configDir Path to config files
	  * @param string $pluginDir Path to plugin files
	  * @return \Smarty
	  */
	public static function getSmarty($templateDirs, $tempDir, $configDir = NULL, $pluginDir = NULL, $forceDisableCache = true)
	{
		$smarty = new \Smarty();

		$smarty->setTemplateDir($templateDirs);

		if (\ServerFunc::isDevelopment())
		{
			$path = explode('/', $_SERVER['DOCUMENT_ROOT']);
			$user = $path[4].'/';
		}
		else
		{
			$user = '';
		}

		$compileDir = $tempDir.$user.'/smarty/compile_dir';

		if (!file_exists($compileDir))
			mkdir($compileDir, 0755, true);
		$smarty->setCompileDir($compileDir);

		$cacheDir = $tempDir.'/smarty/cache_dir';
		if (!file_exists($cacheDir))
			mkdir($cacheDir, 0755, true);
		$smarty->setCacheDir($cacheDir);

		$development = \ServerFunc::isDevelopment();
		$smarty->assign('isDevelopment', $development);

		// Disable caching on development
		if ($development || $forceDisableCache)
		{
			$smarty->caching = \Smarty::CACHING_OFF;
			$smarty->debugging_ctrl = 'URL';
			$smarty->force_compile = true;
		}

		if ($configDir !== NULL)
			$smarty->setConfigDir($configDir);

		if ($pluginDir !== NULL)
			$smarty->addPluginsDir($pluginDir);

		// Use htmlspecialchars by default on all variables (output)
		// circumvent with: {$var nofilter}
		$smarty->escape_html = true;

		return $smarty;
	}
}
?>
