<?php
declare(strict_types=1);
namespace jasonwynn10\GitRollbacks;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\level\Level;
use pocketmine\OfflinePlayer;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class RollbackCommand extends Command {

	/** @var Main */
	protected $plugin;

	public function __construct(Main $plugin) {
		parent::__construct("rollback", "trigger a world rollback on the selected world", "/rollback world <world: string> [saves: int] [force: boolean] OR /rollback player <player: target> [saves: int] [force: boolean]", ["rb"]);
		$this->setPermission("rollback");
		$this->plugin = $plugin;
	}

	/**
	 * @param CommandSender $sender
	 * @param string $commandLabel
	 * @param string[] $args
	 *
	 * @return bool
	 * @throws InvalidCommandSyntaxException|GitException
	 */
	public function execute(CommandSender $sender, string $commandLabel, array $args) {
		if(!$this->testPermission($sender)){
			return false;
		}

		if(count($args) < 2) {
			throw new InvalidCommandSyntaxException();
		}

		if(strtolower($args[0]) === "world") {
			$level = $this->plugin->getServer()->getLevelByName($args[1]);
			if(!$level instanceof Level) {
				$sender->sendMessage(TextFormat::RED."Level not found.");
				return true;
			}

			$force = false;
			if((!isset($args[3]) or $args[3] == false) and $level === $this->plugin->getServer()->getDefaultLevel()) {
				$sender->sendMessage(TextFormat::RED."Are you sure you want to rollback the default world? If so, do /rollback ".$args[0]." ".$args[1]." ".$args[2]." confirm");
				return true;
			}elseif(isset($args[3])) {
				$force = true;
			}
			if($this->plugin->rollbackLevel((int)($args[2] ?? 2), $level, $force)) {
				$sender->sendMessage(TextFormat::GREEN."Rollback Task for world '".$args[1]."' started successfully");
			}else{
				$sender->sendMessage(TextFormat::RED."There was an Error. The requested data could not be rolled back.");
			}
		}elseif(strtolower($args[0]) === "player") {
			$player = $this->plugin->getServer()->getPlayer($args[1]) ?? new OfflinePlayer(Server::getInstance(), $args[1]);
			if(!isset($args[3])) {
				$force = false;
			}else{
				$force = true;
			}

			if($this->plugin->rollbackPlayer((int)($args[2] ?? 2), $player, $force)) {
				$sender->sendMessage(TextFormat::GREEN."Rollback Task for '".$args[1]."' started successfully");
			}else{
				$sender->sendMessage(TextFormat::RED."There was an Error. The requested data could not be rolled back.");
			}
		}else{
			throw new InvalidCommandSyntaxException();
		}
		return true;
	}
}