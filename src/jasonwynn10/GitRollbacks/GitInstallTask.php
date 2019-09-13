<?php
declare(strict_types=1);

namespace jasonwynn10\GitRollbacks;


use pocketmine\plugin\Plugin;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Utils;

class GitInstallTask extends AsyncTask {
	/**
	 * @var string
	 */
	protected $installPath;

	/**
	 * GitInstallTask constructor.
	 *
	 * @param string $installPath
	 */
	public function __construct(string $installPath) {
		$this->installPath = $installPath;
	}

	/**
	 * Actions to execute when run
	 *
	 * @return void
	 */
	public function onRun() {
		if(Utils::getOS() == "win") {
			file_put_contents($this->installPath."PortableGit-2.21.0-64-bit.7z.exe", $handle = fopen("https://github.com/git-for-windows/git/releases/download/v2.23.0.windows.1/PortableGit-2.23.0-64-bit.7z.exe", "r"));
			fclose($handle);
			exec("start PortableGit-2.21.0-64-bit.7z.exe"); // TODO install git without prompt or elevated privileges
		}elseif(Utils::getOS() == "linux") {
			// TODO: linux install
		}elseif(Utils::getOS() == "mac") {
			// TODO: mac install
		}
	}

	public function onCompletion(Server $server) {
		if(\Phar::running(true) !== "") {
			$plugin = $server->getPluginManager()->loadPlugin(\Phar::running(true));
		}else{
			$plugin = $server->getPluginManager()->loadPlugin(dirname(__FILE__, 2));
		}
		if($plugin instanceof Plugin)
			$server->getPluginManager()->enablePlugin($plugin);
		$server->getLogger()->notice("Git has Finished installing.");
	}
}