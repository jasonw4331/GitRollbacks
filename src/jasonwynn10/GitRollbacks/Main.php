<?php
declare(strict_types=1);
namespace jasonwynn10\GitRollbacks;

use pocketmine\event\level\LevelLoadEvent;
use pocketmine\event\level\LevelSaveEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDataSaveEvent;
use pocketmine\IPlayer;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener {
	public function onEnable() : void {
		new Config($this->getDataFolder()."config.yml", Config::YAML, ["use-async" => true]);
		$this->reloadConfig();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getCommandMap()->register("rollback", new RollbackCommand($this));
	}

	/**
	 * @param string $source
	 * @param string $destination
	 * @param GitRepository|null $git
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
						copy($source . '/' . $file, $destination . '/' . $file);
						if($git !== null)
							$git->addFiles([$destination.DIRECTORY_SEPARATOR.$file]);
					}
				}
			}
			closedir($dir);
		}else{
			copy(realpath($source), $destination . '/' . basename($source));
			if($git !== null)
				$git->addFiles([$destination . '/' . basename($source)]);
		}
	}

	/**
	 * @param \DateTime $timestamp
	 * @param Level $level
	 * @param bool $force
	 *
	 * @return bool
	 * @throws GitException
	 */
	public function rollbackLevelFromTimestamp(\DateTime $timestamp, Level $level, bool $force = false) : bool {
		$return = $this->getServer()->unloadLevel($level, $force); // force unload for rollback of default world
		if(!$return)
			return false;
		$git = new GitRepository($this->getDataFolder()."worlds".DIRECTORY_SEPARATOR.$level->getFolderName());
		$commit = $this->findCommitByTimestamp($timestamp, $git);
		if($this->getConfig()->get("use-async", true)) {
			$this->getServer()->getAsyncPool()->submitTask(new RollbackLevelTask($this->getDataFolder()."worlds".DIRECTORY_SEPARATOR.$level->getFolderName(), $level->getFolderName(), $commit, $force));
			return true;
		}
		$git->checkout($commit);
		$count = 1;
		foreach($git->getBranches() ?? [] as $branch) {
			if($branch === "master")
				continue;
			$count = substr($branch,"9");
			$count += (int)$count;
		}
		$git->createBranch("Rollback".$count, true);
		if(!$return) {
			$ret = false;
		}else{
			self::recursiveCopyAddGit($this->getDataFolder()."worlds".DIRECTORY_SEPARATOR.$level->getFolderName(), $level->getProvider()->getPath());
			$ret = true;
		}
		return $ret;
	}

	/**
	 * @param \DateTime $timestamp
	 * @param IPlayer $player
	 * @param bool $force
	 *
	 * @return bool
	 * @throws GitException
	 */
	public function rollbackPlayerFromTimestamp(\DateTime $timestamp, IPlayer $player, bool $force = false) : bool {
		if($player instanceof Player) {
			$return = $player->kick("Your user information is being rolled back", false);
			if(!$return and $force) {
				$player->close($player->getLeaveMessage(), "Your user information is being rolled back");
			}elseif(!$return){
				return false;
			}
		}
		$git = new GitRepository($this->getDataFolder()."players");
		$commit = $this->findCommitByTimestamp($timestamp, $git);
		//$git->checkout($commit); don't rollback all player files
		if($this->getConfig()->get("use-async", true)) {
			$this->getServer()->getAsyncPool()->submitTask(new RollbackPlayerTask($this->getDataFolder()."players", $player->getName(), $commit, $force));
			return true;
		}
		$git->checkoutFile($commit, strtolower($player->getName()).".dat");
		$count = 1;
		foreach($git->getBranches() ?? [] as $branch) {
			if($branch === "master")
				continue;
			$count = substr($branch,"9");
			$count += (int)$count;
		}
		$git->createBranch("Rollback".$count, true);
		self::recursiveCopyAddGit($this->getDataFolder()."players", $this->getServer()->getDataPath()."players".DIRECTORY_SEPARATOR);
		return true;
	}

	/**
	 * @param string $commit
	 * @param Level $level
	 * @param bool $force
	 *
	 * @return bool
	 * @throws GitException
	 */
	public function rollbackLevelFromCommit(string $commit, Level $level, bool $force = false) : bool {
		$return = $this->getServer()->unloadLevel($level, $force); // force unload for rollback of default world
		if(!$return)
			return false;
		$git = new GitRepository($this->getDataFolder()."worlds".DIRECTORY_SEPARATOR.$level->getFolderName());
		$git->checkout($commit);
		$count = 1;
		foreach($git->getBranches() ?? [] as $branch) {
			if($branch === "master")
				continue;
			$count = substr($branch, 9);
			$count += (int)$count;
		}
		$git->createBranch("Rollback".$count, true);
		if($this->getConfig()->get("use-async", true)) {
			$this->getServer()->getAsyncPool()->submitTask(new RollbackLevelTask($this->getDataFolder()."worlds".DIRECTORY_SEPARATOR.$level->getFolderName(), $level->getFolderName(), $commit, $force));
		}
		self::recursiveCopyAddGit($this->getDataFolder()."worlds".DIRECTORY_SEPARATOR.$level->getFolderName(), $level->getProvider()->getPath());
		return true;
	}

	/**
	 * @param string $commit
	 * @param IPlayer $player
	 * @param bool $force
	 *
	 * @return bool
	 * @throws GitException
	 */
	public function rollbackPlayerFromCommit(string $commit, IPlayer $player, bool $force = false) : bool {
		if($player instanceof Player) {
			$return = $player->kick("Your user information is being rolled back", false);
			if(!$return and $force) {
				$player->close($player->getLeaveMessage(), "Your user information is being rolled back");
			}elseif(!$return) {
				return false;
			}
		}
		$git = new GitRepository($this->getDataFolder()."players");
		//$git->checkout($commit); don't rollback all player files
		if($this->getConfig()->get("use-async", true)) {
			$this->getServer()->getAsyncPool()->submitTask(new RollbackPlayerTask($this->getDataFolder()."players", $player->getName(), $commit, $force));
			return true;
		}
		$git->checkoutFile($commit, strtolower($player->getName()).".dat");
		$count = 1;
		foreach($git->getBranches() ?? [] as $branch) {
			if($branch === "master")
				continue;
			$count = substr($branch,"9");
			$count += (int)$count;
		}
		$git->createBranch("Rollback".$count, true);
		self::recursiveCopyAddGit($this->getDataFolder()."players", $this->getServer()->getDataPath()."players".DIRECTORY_SEPARATOR);
		return true;
	}

	/**
	 * @param Level $level
	 *
	 * @return string
	 * @throws GitException
	 */
	public function getLastLevelCommit(Level $level) : string {
		$git = new GitRepository($this->getDataFolder()."worlds".DIRECTORY_SEPARATOR.$level->getFolderName());
		return $git->getLastCommitId();
	}

	/**
	 * @param IPlayer|null $player
	 *
	 * @return string
	 * @throws GitException
	 */
	public function getLastPlayerCommit(?IPlayer $player = null) : string {
		$git = new GitRepository($this->getDataFolder()."players"); // TODO find last commit that involves specific file
		return $git->getLastCommitId();
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
	 *
	 * @throws \Exception
	 */
	public function onWorldSave(LevelSaveEvent $event) : void {
		$gitFolder = $this->getDataFolder()."worlds".DIRECTORY_SEPARATOR.$event->getLevel()->getFolderName();
		$worldFolder = $event->getLevel()->getProvider()->getPath();
		$levelName = $event->getLevel()->getFolderName();
		$timestamp = (new \DateTime())->format('Y-m-d H:i:s');
		if($this->getConfig()->get("use-async", true)) {
			$this->getServer()->getAsyncPool()->submitTask(new GitCommitTask($gitFolder, $worldFolder, $timestamp, $levelName));
			return;
		}
		$git = new GitRepository($gitFolder);
		Main::recursiveCopyAddGit($worldFolder, $gitFolder, $git);
		$git->addAllChanges();
		$git->commit($levelName." ".$timestamp);
	}

	public function onPlayerSave(PlayerDataSaveEvent $event) {
		$gitFolder = $this->getDataFolder()."players";
		$playerFile = $this->getServer()->getDataPath()."players".DIRECTORY_SEPARATOR.$event->getPlayerName().".dat";
		$playerName = $event->getPlayerName();
		$timestamp = (new \DateTime())->format('Y-m-d H:i:s');
		if($this->getConfig()->get("use-async", true)) {
			$this->getServer()->getAsyncPool()->submitTask(new GitCommitTask($gitFolder, $playerFile, $timestamp, $playerName));
			return;
		}
		$git = new GitRepository($gitFolder);
		Main::recursiveCopyAddGit($playerFile, $gitFolder, $git);
		$git->addAllChanges();
		$git->commit($playerName." ".$timestamp);
	}
}