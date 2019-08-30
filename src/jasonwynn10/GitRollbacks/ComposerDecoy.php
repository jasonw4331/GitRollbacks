<?php
declare(strict_types=1);

namespace jasonwynn10\GitRollbacks;

use pocketmine\utils\MainLogger;

class ComposerDecoy {
	/**
	 * Dummy function called to invoke the pocketmine class loader
	 */
	public static function load(): void {
	}

	/**
	 * Require the composer autoload file whenever this class is loaded by the pocketmine class loader
	 */
	public static function onClassLoaded(): void {
		if(!defined('jasonwynn10\GitRollbacks\COMPOSER_AUTOLOADER_PATH')) {
			if(\Phar::running(true) !== "") {
				define('jasonwynn10\GitRollbacks\COMPOSER_AUTOLOADER_PATH',
					\Phar::running(true) . "/vendor/autoload.php");
			} elseif(is_file($path = dirname(__DIR__, 3) . "/vendor/autoload.php")) {
				define('jasonwynn10\GitRollbacks\COMPOSER_AUTOLOADER_PATH', $path);
			} else {
				MainLogger::getLogger()->debug("Composer autoloader not found.");
				MainLogger::getLogger()->debug("Please install/update Composer dependencies or use provided releases.");
				trigger_error("Couldn't find composer autoloader", E_USER_ERROR);

				return;
			}
		}
		require_once(\jasonwynn10\GitRollbacks\COMPOSER_AUTOLOADER_PATH);
	}
}