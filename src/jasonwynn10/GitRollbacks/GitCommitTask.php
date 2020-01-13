<?php
declare(strict_types=1);
namespace jasonwynn10\GitRollbacks;

use pocketmine\scheduler\AsyncTask;

class GitCommitTask extends AsyncTask {

	/** @var string */
	private $copyFolder, $timestamp, $gitFolder, $levelName;

	public function __construct(string $gitFolder, string $worldFolder, string $timestamp, string $levelName) {
		$this->gitFolder = $gitFolder;
		$this->copyFolder = $worldFolder;
		$this->timestamp = $timestamp;
		$this->levelName = $levelName;
	}

	/**
	 * Actions to execute when run
	 *
	 * @return void
	 * @throws GitException
	 */
	public function onRun() {
		$git = new GitRepository($this->gitFolder);
		Main::recursiveCopyAddGit($this->copyFolder, $this->gitFolder, $git);
		$git->addAllChanges();
		$git->commit($this->levelName." ".$this->timestamp);
	}
}