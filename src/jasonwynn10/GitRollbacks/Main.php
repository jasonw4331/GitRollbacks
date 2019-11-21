<?php
declare(strict_types=1);
namespace jasonwynn10\GitRollbacks;

use pocketmine\event\level\LevelLoadEvent;
use pocketmine\event\level\LevelSaveEvent;
use pocketmine\event\Listener;
use pocketmine\level\Level;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginException;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\Utils;

class Main extends PluginBase implements Listener {
	public function onLoad() : void {
		if(!in_array(Utils::getOS(), ["win", "linux", "mac"])) {
			throw new PluginException("GitRollbacks is currently designed to function on Windows and Linux based devices. Your device is not compatible."); // TODO: BSD support
		}
		if(!$this->testGit()) {
			Server::getInstance()->getAsyncPool()->submitTask(new GitInstallTask($this->getDataFolder()));
			throw new PluginException("Git is not installed. Plugin startup will be delayed until the installation is completed.");
		}
	}

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
						$git->addFiles([$destination.DIRECTORY_SEPARATOR.$file]);
				}
			}
		}
		closedir($dir);
	}

	/**
	 * @param \DateTime $timestamp
	 * @param Level $level
	 * @param bool $force
	 *
	 * @return bool
	 * @throws GitException
	 */
	public function rollbackFromTimestamp(\DateTime $timestamp, Level $level, bool $force = false) : bool {
		$git = new GitRepository($this->getDataFolder().$level->getFolderName());
		$commit = $this->findCommitByTimestamp($timestamp, $git);
		if($this->getConfig()->get("use-async", true)) {
			$this->getServer()->getAsyncPool()->submitTask(new RollbackTask($this->getDataFolder().$level->getFolderName(), $level->getFolderName(), $commit, $force));
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
		$return = $this->getServer()->unloadLevel($level, $force); // force unload for rollback of default world
		if(!$return)
			return false;
		self::recursiveCopyAddGit($this->getDataFolder().$level->getFolderName(), $level->getProvider()->getPath());
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
	public function rollbackFromCommit(string $commit, Level $level, bool $force = false) : bool {
		$git = new GitRepository($this->getDataFolder().$level->getFolderName());
		$git->checkout($commit);
		$count = 1;
		foreach($git->getBranches() ?? [] as $branch) {
			if($branch === "master")
				continue;
			$count = substr($branch, 9);
			$count += (int)$count;
		}
		$git->createBranch("Rollback".$count, true);
		$return = $this->getServer()->unloadLevel($level, $force); // force unload for rollback of default world
		if(!$return)
			return false;
		if($this->getConfig()->get("use-async", true)) {
			$this->getServer()->getAsyncPool()->submitTask(new RollbackTask($this->getDataFolder().$level->getFolderName(), $level->getFolderName(), $commit, $force));
		}
		self::recursiveCopyAddGit($this->getDataFolder().$level->getFolderName(), $level->getProvider()->getPath());
		return true;
	}

	/**
	 * @param Level $level
	 *
	 * @return string
	 * @throws GitException
	 */
	public function getLastCommit(Level $level) : string {
		$git = new GitRepository($this->getDataFolder().$level->getFolderName());
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
			GitRepository::init($this->getDataFolder().$level->getFolderName());
		}catch(GitException $e) {
			$initialCommit = false;
		}

		$git = new GitRepository($this->getDataFolder().$level->getFolderName());

		self::recursiveCopyAddGit($level->getProvider()->getPath(), $this->getDataFolder().$level->getFolderName(), $git);

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
		$gitFolder = $this->getDataFolder().$event->getLevel()->getFolderName();
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

	/**
	 * @return bool
	 */
	private function testGit() : bool {
		switch(Utils::getOS()) {
			/** @noinspection PhpMissingBreakStatementInspection */
			case "win":
				$ret = 0;
				$cmd = 'git help -g';
				try{
					exec($cmd . ' 2>&1', $output, $ret);
					var_dump($output);
				}finally{
					return ($ret !== 0);
				}
			break;
			case "linux":
				$ret = 0;
				$cmd = 'git help -g';
				try{
					exec($cmd . ' 2>&1', $output, $ret);
					var_dump($output);
				}finally{
					return $ret !== 0;
				}
			break;
			case "mac": // TODO: is mac different?
			break;
			default:
				throw new PluginException("The OS of this device does not support git installation.");
		}
	}
}