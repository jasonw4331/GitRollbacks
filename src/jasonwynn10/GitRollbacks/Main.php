<?php
declare(strict_types=1);
namespace jasonwynn10\GitRollbacks;

use pocketmine\event\level\LevelLoadEvent;
use pocketmine\event\level\LevelSaveEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDataSaveEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\IPlayer;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener {
	public function onEnable() : void {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getCommandMap()->register("rollback", new RollbackCommand($this));
	}

	/**
	 * @param string $source
	 * @param string $destination
	 * @param GitRepository $git
	 *
	 * @throws GitException
	 */
	public static function recursiveCopyAddGit(string $source, string $destination, GitRepository $git = null) : void {
		if(is_dir($source)) {
			$dir = opendir($source);
			@mkdir($destination);
			while(false !== ( $file = readdir($dir)) ) {
				if (( $file != '.' ) && ( $file != '..' )) {
					if ( is_dir($source . '/' . $file) ) {
						self::recursiveCopyAddGit($source . '/' . $file, $destination . '/' . $file);
					}else {
						touch($destination . '/' . $file);
						copy($source . '/' . $file, $destination . '/' . $file);
						if($git !== null)
							$git->addFiles([$destination.DIRECTORY_SEPARATOR.$file]);
					}
				}
			}
			closedir($dir);
		}else{
			touch($destination . '/' . basename($source));
			copy(realpath($source), $destination . '/' . basename($source));
			if($git !== null)
				$git->addFiles([$destination . '/' . basename($source)]);
		}
	}

	/**
	 * @param int $saveCount
	 * @param Level $level
	 * @param bool $force
	 *
	 * @return bool
	 * @throws GitException
	 */
	public function rollbackLevel(int $saveCount, Level $level, bool $force = false) : bool {
		$return = $this->getServer()->unloadLevel($level, $force); // force unload for rollback of default world
		if(!$return)
			return false;
		$git = new GitRepository($this->getDataFolder()."worlds".DIRECTORY_SEPARATOR.$level->getFolderName());
		$commit = $git->getLastCommitId($saveCount);
		if(!is_string($commit)) {
			return false;
		}
		$git->checkout($commit);
		$count = 1;
		foreach($git->getBranches() ?? [] as $branch) {
			if($branch === "master")
				continue;
			$count = substr($branch, 8);
			$count += (int)$count;
		}
		$git->createBranch("Rollback".$count, true);
		if($this->getServer()->isRunning()) {
			$this->getServer()->getAsyncPool()->submitTask(new RollbackLevelTask($this->getDataFolder()."worlds".DIRECTORY_SEPARATOR.$level->getFolderName(), $level->getFolderName(), $commit, $force));
		}
		self::recursiveCopyAddGit($this->getDataFolder()."worlds".DIRECTORY_SEPARATOR.$level->getFolderName(), $level->getProvider()->getPath());
		return true;
	}

	/**
	 * @param int $saveCount
	 * @param IPlayer $player
	 * @param bool $force
	 *
	 * @return bool
	 * @throws GitException
	 */
	public function rollbackPlayer(int $saveCount, IPlayer $player, bool $force = false) : bool {
		if($player->isOnline()) {
			$return = $player->kick("Your player data is being rolled back", false);
			if(!$return and $force) {
				$player->close($player->getLeaveMessage(), "Your player data is being rolled back");
			}elseif(!$return) {
				return false;
			}
		}
		$git = new GitRepository($this->getDataFolder()."players");
		$commit = $git->getLastFileCommitId(strtolower($player->getName()).".dat", $saveCount);
		if(!is_string($commit)) {
			return false;
		}
		if($this->getServer()->isRunning()) {
			$this->getServer()->getAsyncPool()->submitTask(new RollbackPlayerTask($this->getDataFolder()."players", $player->getName(), $commit, $force));
			return true;
		}
		$git->checkoutFile($commit, strtolower($player->getName()).".dat");
		$count = 1;
		foreach($git->getBranches() ?? [] as $branch) {
			if($branch === "master")
				continue;
			$count = substr($branch, 8);
			$count += (int)$count;
		}
		$git->createBranch("Rollback".$count, true);
		self::recursiveCopyAddGit($this->getDataFolder()."players", $this->getServer()->getDataPath()."players".DIRECTORY_SEPARATOR);
		return true;
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
			GitRepository::init($this->getDataFolder()."worlds".DIRECTORY_SEPARATOR.$level->getFolderName());
		}catch(GitException $e) {
			$initialCommit = false;
		}

		$git = new GitRepository($this->getDataFolder()."worlds".DIRECTORY_SEPARATOR.$level->getFolderName());

		self::recursiveCopyAddGit($level->getProvider()->getPath(), $this->getDataFolder()."worlds".DIRECTORY_SEPARATOR.$level->getFolderName(), $git);

		if($initialCommit) {
			$git->addAllChanges();
			$git->commit("First Backup");
		}
	}

	/**
	 * @param LevelSaveEvent $event
	 */
	public function onWorldSave(LevelSaveEvent $event) : void {
		$gitFolder = $this->getDataFolder()."worlds".DIRECTORY_SEPARATOR.$event->getLevel()->getFolderName();
		$worldFolder = $event->getLevel()->getProvider()->getPath();
		$levelName = $event->getLevel()->getFolderName();
		$timestamp = (new \DateTime())->format('Y-m-d H:i:s');
		if($this->getServer()->isRunning()) {
			$this->getServer()->getAsyncPool()->submitTask(new GitCommitAsyncTask($gitFolder, $worldFolder, $timestamp, $levelName));
			return;
		}
		$this->getScheduler()->scheduleTask(new GitCommitTask($gitFolder, $worldFolder, $timestamp, $levelName));
	}

	/**
	 * @param PlayerJoinEvent $event
	 */
	public function onPlayerJoin(PlayerJoinEvent $event) {
		try{
			GitRepository::init($this->getDataFolder()."players");
			$git = new GitRepository($this->getDataFolder()."players");
			self::recursiveCopyAddGit($this->getServer()->getDataPath()."players", $this->getDataFolder()."players", $git);
			$git->addAllChanges();
			$git->commit("First Backup");
		}catch(GitException $e) {
			// do nothing
		}
	}

	/**
	 * @param PlayerDataSaveEvent $event
	 */
	public function onPlayerSave(PlayerDataSaveEvent $event) {
		$gitFolder = $this->getDataFolder()."players";
		$playerFile = $this->getServer()->getDataPath()."players".DIRECTORY_SEPARATOR.strtolower($event->getPlayerName()).".dat";
		$playerName = $event->getPlayerName();
		$timestamp = (new \DateTime())->format('Y-m-d H:i:s');
		if($this->getServer()->isRunning()) {
			$this->getServer()->getAsyncPool()->submitTask(new GitCommitAsyncTask($gitFolder, $playerFile, $timestamp, $playerName));
			return;
		}
		$this->getScheduler()->scheduleTask(new GitCommitTask($gitFolder, $playerFile, $timestamp, $playerName));
	}
}