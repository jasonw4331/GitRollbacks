<?php
declare(strict_types=1);
namespace jasonwynn10\GitRollbacks;

use pocketmine\level\Level;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class RollbackLevelTask extends AsyncTask {

	/** @var string */
	private $commitHash, $gitFolder, $levelName, $levelPath;

	public function __construct(string $gitFolder, string $levelName, string $commitHash, bool $force) {
		$this->gitFolder = $gitFolder;
		$this->commitHash = $commitHash;
		$this->levelName = $levelName;
		$level = Server::getInstance()->getLevelByName($levelName);
		if($level instanceof Level) {
			$this->levelPath = $level->getProvider()->getPath();
			Server::getInstance()->unloadLevel($level, $force); // force unload for rollback of default world
		}
	}

	/**
	 * Actions to execute when run
	 *
	 * @return void
	 * @throws GitException
	 */
	public function onRun() {
		$git = new GitRepository($this->gitFolder);
		$git->reset($this->commitHash);
		Main::recursiveCopyAddGit($this->gitFolder, $this->levelPath);
	}
}