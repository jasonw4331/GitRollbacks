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
			file_put_contents($this->installPath."PortableGit.zip", $handle = fopen("https://github.com/git-for-windows/git/releases/download/v2.24.0.windows.2/MinGit-2.24.0.2-64-bit.zip", "r"));
			fclose($handle);
			$archive = new \ZipArchive();
			if($archive->open($this->installPath."PortableGit.zip")) {
				$archive->extractTo($this->installPath);
				$archive->close();
			}
			exec("set PATH=%PATH%;".$this->installPath);
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