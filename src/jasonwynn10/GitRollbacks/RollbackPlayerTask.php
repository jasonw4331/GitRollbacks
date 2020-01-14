<?php
declare(strict_types=1);
namespace jasonwynn10\GitRollbacks;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class RollbackPlayerTask extends AsyncTask {
	/** @var string */
	private $commitHash, $gitFolder, $playerName;
	/** @var bool */
	private $force;

	/**
	 * RollbackPlayerTask constructor.
	 *
	 * @param string $gitFolder
	 * @param string $playerName
	 * @param string $commitHash
	 * @param bool $force
	 */
	public function __construct(string $gitFolder, string $playerName, string $commitHash, bool $force) {
		$this->gitFolder = $gitFolder;
		$this->commitHash = $commitHash;
		$this->playerName = $playerName;
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
		$git->checkoutFile($this->commitHash, strtolower($this->playerName).".dat");
		$count = 1;
		foreach($git->getBranches() ?? [] as $branch) {
			if($branch === "master")
				continue;
			$count = substr($branch,"9");
			$count += (int)$count;
		}
		$git->createBranch("Rollback".$count, true);
		Main::recursiveCopyAddGit($this->gitFolder, Server::getInstance()->getDataPath()."players".DIRECTORY_SEPARATOR);
	}
}