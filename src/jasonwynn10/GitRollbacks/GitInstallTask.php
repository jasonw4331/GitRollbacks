<?php
declare(strict_types=1);

namespace jasonwynn10\GitRollbacks;


use pocketmine\plugin\Plugin;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\MainLogger;
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
			exec("apt-get install git 2>&1", $output, $ret);
			if($ret != 0)
				exec("sudo apt-get install git 2>&1", $output, $ret);
			if($ret != 0) {
				if(!is_dir($this->installPath."git-2.25.0")) {
					if(!file_exists($this->installPath."Git.tar.gz")) {
						exec("cd '".$this->installPath."' && curl -o Git.tar.gz -LO https://github.com/git/git/archive/v2.25.0.tar.gz 2>&1", $output, $ret);
						MainLogger::getLogger()->info("Git Tarball Downloaded");
					}
					$archive = new \PharData($this->installPath."Git.tar.gz");
					$archive->decompress();
					$archive->extractTo($this->installPath);
					unlink($this->installPath."Git.tar.gz");
					unlink($this->installPath."Git.tar");
					MainLogger::getLogger()->info("Git Tarball Extracted");
				}
				exec("cd '".$this->installPath."git-2.25.0' && make prefix=\$HOME/git profile-fast-install 2>&1", $output, $ret);
				var_dump($output, $ret);
				if($ret != 0) {
					throw new GitException("Git is unable to compile due to missing requirements");
				}
			}

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