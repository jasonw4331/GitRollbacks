<?php
declare(strict_types=1);
namespace jasonwynn10\GitRollbacks;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class RollbackLevelTask extends AsyncTask {

	/** @var string */
	private $commitHash, $gitFolder, $levelName;
	/** @var bool */
	private $force;

	public function __construct(string $gitFolder, string $levelName, string $commitHash, bool $force) {
		$this->gitFolder = $gitFolder;
		$this->commitHash = $commitHash;
		$this->levelName = $levelName;
		$this->force = $force;
	}

	/**
	 * Actions to execute when run
	 *
	 * @return void
	 * @throws GitException
	 */
	public function onRun() {
		$git = new GitRepository($this->gitFolder);
		//$git->checkout($this->commitHash);
		$git->reset($this->commitHash);
		$count = 1;
		foreach($git->getBranches() ?? [] as $branch) {
			if($branch === "master")
				continue;
			$count = substr($branch, 8);
			$count += (int)$count;
		}
		$git->createBranch("Rollback".$count, true);
		$level = Server::getInstance()->getLevelByName($this->levelName);
		$return = Server::getInstance()->unloadLevel($level, $this->force); // force unload for rollback of default world
		if(!$return)
			return;
		Main::recursiveCopyAddGit($this->gitFolder, $level->getProvider()->getPath());
	}
}