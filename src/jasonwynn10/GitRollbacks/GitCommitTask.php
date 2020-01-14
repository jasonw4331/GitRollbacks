<?php
declare(strict_types=1);
namespace jasonwynn10\GitRollbacks;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class GitCommitTask extends AsyncTask {

	/** @var string */
	private $copyPath, $timestamp, $gitFolder, $commitMessage;

	/**
	 * GitCommitTask constructor.
	 *
	 * @param string $gitFolder
	 * @param string $copyPath
	 * @param string $timestamp
	 * @param string $commitMessage
	 */
	public function __construct(string $gitFolder, string $copyPath, string $timestamp, string $commitMessage) {
		$this->gitFolder = $gitFolder;
		$this->copyPath = $copyPath;
		$this->timestamp = $timestamp;
		$this->commitMessage = $commitMessage;
	}

	/**
	 * @throws GitException
	 */
	public function onRun() {
		$git = new GitRepository($this->gitFolder);
		Main::recursiveCopyAddGit($this->copyPath, $this->gitFolder, $git);
		$git->addAllChanges();
		$git->commit($this->commitMessage." ".$this->timestamp);
	}

	/**
	 * @param Server $server
	 */
	public function onCompletion(Server $server) {
		$server->getLogger()->debug("Information Committed");
	}
}