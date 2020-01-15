<?php
declare(strict_types=1);
namespace jasonwynn10\GitRollbacks;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\CommandException;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\level\Level;
use pocketmine\OfflinePlayer;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class RollbackCommand extends Command {

	/** @var Main */
	protected $plugin;

	public function __construct(Main $plugin) {
		parent::__construct("rollback", "trigger a world rollback on the selected world", "/rollback world <world: string> <commit: string> OR /rollback world <world: string> <timestamp: string> OR /rollback player <player: target> <commit: string> OR /rollback player <player: target> <timestamp: string>", ["rb"]);
		$this->setPermission("rollback");
		$this->plugin = $plugin;
	}

	/**
	 * @param CommandSender $sender
	 * @param string $commandLabel
	 * @param string[] $args
	 *
	 * @return bool
	 * @throws CommandException|GitException
	 */
	public function execute(CommandSender $sender, string $commandLabel, array $args) {
		if(!$this->testPermission($sender)){
			return false;
		}

		if(count($args) < 2) {
			throw new InvalidCommandSyntaxException();
		}

		if(strtolower($args[0]) === "world") {
			$levelName = $args[1];
			$level = $this->plugin->getServer()->getLevelByName($levelName);
			if($level instanceof Level) {
				$sender->sendMessage(TextFormat::RED."Level not found.");
				return true;
			}

			$force = false;
			if(!isset($args[3]) and $level === $this->plugin->getServer()->getDefaultLevel()) {
				$sender->sendMessage(TextFormat::RED."Are you sure you want to rollback the default world? If so, do /rollback ".$args[0]." ".$args[1]." ".$args[2]." confirm");
				return true;
			}elseif(isset($args[3])) {
				$force = true;
			}

			if(($dateTime = $this->isTimestamp($args[2])) instanceof \DateTime) {
				$this->plugin->rollbackLevelFromTimestamp($dateTime, $level, $force);
				$sender->sendMessage(TextFormat::GREEN."Rollback Task for world '".$levelName."' started successfully");
			}else{
				$commitHash = strtolower($args[2]);
				if($commitHash === "last")
					$commitHash = $this->plugin->getLastLevelCommit($level);
				if(strlen($commitHash) !== 7 and strlen($commitHash) !== 40) {
					$sender->sendMessage(TextFormat::YELLOW."Commit hashes must be 7 or 40 characters");
					return true;
				}
				$this->plugin->rollbackLevelFromCommit($commitHash, $level, $force);
				$sender->sendMessage(TextFormat::GREEN."Rollback Task for world '".$levelName."' started successfully");
			}
		}elseif(strtolower($args[0]) === "player") {
			$player = $this->plugin->getServer()->getPlayer($args[1]) ?? new OfflinePlayer(Server::getInstance(), $args[1]);
			if(!isset($args[3])) {
				$force = false;
			}else{
				$force = true;
			}

			if(($dateTime = $this->isTimestamp($args[2])) instanceof \DateTime) {
				$this->plugin->rollbackPlayerFromTimestamp($dateTime, $player, $force);
				$sender->sendMessage(TextFormat::GREEN."Rollback Task for '".$args[1]."' started successfully");
			}else{
				$commitHash = strtolower($args[2]);
				if($commitHash === "last")
					$commitHash = $this->plugin->getLastPlayerCommit($player);
				if(strlen($commitHash) !== 7 and strlen($commitHash) !== 40) {
					$sender->sendMessage(TextFormat::YELLOW."Commit hashes must be 7 or 40 characters");
					return true;
				}
				$this->plugin->rollbackPlayerFromCommit($commitHash, $player, $force);
				$sender->sendMessage(TextFormat::GREEN."Rollback Task for '".$args[1]."' started successfully");
			}
		}else{
			throw new CommandException();
		}
		return true;
	}

	private function isTimestamp(string $value) : ?\DateTime {
		if(strtolower($value) == "now")
			return new \DateTime();
		$dateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
		return $dateTime === false ? null : $dateTime;
	}
}