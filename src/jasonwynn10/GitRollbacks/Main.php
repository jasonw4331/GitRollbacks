<?php
declare(strict_types=1);

namespace jasonwynn10\GitRollbacks;

use Cz\Git\GitException;
use Cz\Git\GitRepository;
use pocketmine\event\level\LevelLoadEvent;
use pocketmine\event\level\LevelSaveEvent;
use pocketmine\event\Listener;
use pocketmine\level\Level;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener {
	public function onLoad() {
		ComposerDecoy::load();
	}

	public function onEnable() : void {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		// TODO: rollback command
	}

	/**
	 * @param string $source
	 * @param string $destination
	 * @param GitRepository|null $git
	 *
	 * @throws GitException
	 */
	public static function recursiveCopyAddGit(string $source, string $destination, GitRepository $git = null) : void {
		$dir = opendir($source);
		@mkdir($destination);
		while(false !== ( $file = readdir($dir)) ) {
			if (( $file != '.' ) && ( $file != '..' )) {
				if ( is_dir($source . '/' . $file) ) {
					self::recursiveCopyAddGit($source . '/' . $file, $destination . '/' . $file);
				}
				else {
					copy($source . '/' . $file, $destination . '/' . $file);
					if($git !== null)
						$git->addFile($destination.DIRECTORY_SEPARATOR.$file);
				}
			}
		}
		closedir($dir);
	}

	/**
	 * @param \DateTime $timestamp
	 * @param Level $level
	 *
	 * @throws GitException
	 */
	public function rollbackFromTimestamp(\DateTime $timestamp, Level $level) : void {
		$git = new GitRepository($this->getDataFolder().$level->getFolderName());
		$commit = $this->findCommitByTimestamp($timestamp, $git);
		$git->checkout($commit);
		$count = 1;
		foreach($git->getBranches() ?? [] as $branch) {
			if($branch === "master")
				continue;
			$count = substr($branch,"9");
			$count += (int)$count;
		}
		$git->createBranch("Rollback".$count, true);
		$this->getServer()->unloadLevel($level, true); // force unload for rollback of default world
		self::recursiveCopyAddGit($this->getDataFolder().$level->getFolderName(), $level->getProvider()->getPath());
	}

	/**
	 * @param string $commit
	 * @param Level $level
	 *
	 * @throws GitException
	 */
	public function rollbackFromCommit(string $commit, Level $level) : void {
		$git = new GitRepository($this->getDataFolder().$level->getFolderName());
		$git->checkout($commit);
		$count = 1;
		foreach($git->getBranches() ?? [] as $branch) {
			if($branch === "master")
				continue;
			$count = substr($branch,"9");
			$count += (int)$count;
		}
		$git->createBranch("Rollback".$count, true);
		self::recursiveCopyAddGit($this->getDataFolder().$level->getFolderName(), $level->getProvider()->getPath());
	}

	/**
	 * @param \DateTime $timestamp
	 * @param GitRepository $git
	 *
	 * @return string
	 * @throws GitException
	 */
	public static function findCommitByTimestamp(\DateTime $timestamp, GitRepository $git) : string {
		$timestamp = $timestamp->format('Y-m-d H:i:s');
		$output = $git->execute(['log', '--grep='.$timestamp]);
		$commitHash = substr($output[0], strlen("commit "));
		return $commitHash;
	}

	/**
	 * @param LevelLoadEvent $event
	 *
	 * @throws GitException
	 */
	public function onWorldLoad(LevelLoadEvent $event) : void {
		$level = $event->getLevel();
		try{
			$initialCommit = true;
			GitRepository::init($this->getDataFolder().$level->getFolderName());
		}catch(GitException $e) {
			$initialCommit = false;
		}

		$git = new GitRepository($this->getDataFolder().$level->getFolderName());

		self::recursiveCopyAddGit($level->getProvider()->getPath(), $this->getDataFolder().$level->getFolderName(), $git);

		if($initialCommit) {
			$git->addAllChanges();
			$git->commit("First Save");
		}
	}

	/**
	 * @param LevelSaveEvent $event
	 *
	 * @throws \Exception
	 */
	public function onWorldSave(LevelSaveEvent $event) : void {
		$gitFolder = $this->getDataFolder().$event->getLevel()->getFolderName();
		$worldFolder = $event->getLevel()->getProvider()->getPath();
		$levelName = $event->getLevel()->getFolderName();
		$time = (new \DateTime())->format('Y-m-d H:i:s');

		$this->getServer()->getAsyncPool()->submitTask(new GitCommitTask($gitFolder, $worldFolder, $time, $levelName));
	}
}