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
			if(!file_exists($this->installPath."PortableGit.zip")) {
				file_put_contents($this->installPath."PortableGit.zip", $handle = fopen("https://github.com/git-for-windows/git/releases/download/v2.24.0.windows.2/MinGit-2.24.0.2-64-bit.zip", "r"));
				fclose($handle);
			}
			if(!file_exists($this->installPath.DIRECTORY_SEPARATOR."git")) {
				$archive = new \ZipArchive();
				if($archive->open($this->installPath."PortableGit.zip")) {
					$archive->extractTo($this->installPath.DIRECTORY_SEPARATOR."git");
					$archive->close();
				}
			}
			exec("set PATH=%PATH%;".$this->installPath.DIRECTORY_SEPARATOR."git".DIRECTORY_SEPARATOR."cmd");
			//GitRepository::setGitInstallation($this->installPath.DIRECTORY_SEPARATOR."git".DIRECTORY_SEPARATOR."cmd".DIRECTORY_SEPARATOR."git.exe");
		}elseif(Utils::getOS() == "linux") {
			try{
				exec("apt-get install git", $output, $ret);
				var_dump($output, $ret);
				if($ret !== 0) {
					// TODO: download source and compile manually
				}
			}catch(\Exception $e){}
		}elseif(Utils::getOS() == "mac") {
			// TODO: mac install
		}
	}

	public function onCompletion(Server $server) {
		$server->getLogger()->notice("Git has Finished installing");
		if(\Phar::running(true) !== "") {
			$plugin = $server->getPluginManager()->loadPlugin(\Phar::running(true));
		}else{
			$plugin = $server->getPluginManager()->loadPlugin(dirname(__FILE__, 4));
		}
		if($plugin instanceof Plugin)
			$server->getPluginManager()->enablePlugin($plugin);
	}
}