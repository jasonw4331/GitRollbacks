<?php
declare(strict_types=1);
namespace jasonwynn10\GitRollbacks;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class RollbackPlayerTask extends AsyncTask {
	/** @var string */
	private $commitHash, $gitFolder, $playerName, $serverPath;
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
		$this->serverPath = Server::getInstance()->getDataPath();
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
		Main::recursiveCopyAddGit($this->gitFolder, $this->serverPath."players".DIRECTORY_SEPARATOR);
	}
}