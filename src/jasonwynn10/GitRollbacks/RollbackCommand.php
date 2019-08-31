<?php
declare(strict_types=1);

namespace jasonwynn10\GitRollbacks;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\CommandException;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\level\Level;
use pocketmine\utils\TextFormat;

class RollbackCommand extends Command {

	/** @var Main */
	protected $plugin;

	public function __construct(Main $plugin) {
		parent::__construct("rollback", "trigger a world rollback on the selected world", "/rollback <world: string> <commit|timestamp: string>", ["rb"]);
		$this->setPermission("rollback");
		$this->plugin = $plugin;
	}

	/**
	 * @param CommandSender $sender
	 * @param string $commandLabel
	 * @param string[] $args
	 *
	 * @return bool
	 * @throws CommandException
	 */
	public function execute(CommandSender $sender, string $commandLabel, array $args) {
		if(!$this->testPermission($sender)){
			return false;
		}

		if(count($args) < 2) {
			throw new InvalidCommandSyntaxException();
		}

		$levelName = $args[0];
		$level = $this->plugin->getServer()->getLevelByName($levelName);
		if($level instanceof Level) {
			$sender->sendMessage(TextFormat::RED."Level not found");
			return true;
		}

		if(!isset($args[2]) and $level === $this->plugin->getServer()->getDefaultLevel()) {
			$sender->sendMessage(TextFormat::RED."Are you sure you want to rollback the default world? If so, do /rollback ".$args[0]." ".$args[1]." confirm");
			return true;
		}

		if(($dateTime = $this->isTimestamp($args[1])) instanceof \DateTime) {
			$this->plugin->rollbackFromTimestamp($dateTime, $level); // TODO use async task
			$sender->sendMessage(TextFormat::GREEN."Rollback Task for world '".$levelName."' started successfully");
		}else{
			$commitHash = $args[1];
			if($commitHash === "last")
				$commitHash = $this->plugin->getLastCommit($level);
			$this->plugin->rollbackFromCommit($commitHash, $level); // TODO use async task
			$sender->sendMessage(TextFormat::GREEN."Rollback Task for world '".$levelName."' started successfully");
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