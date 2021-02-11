<?php

namespace Aboshxm2\privateroom\_class;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\{Command, CommandSender};
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\math\Vector3;
use pocketmine\event\block\BlockBreakEvent;
use Aboshxm2\privateroom\object\Room;

class PrivateRoom extends PluginBase implements Listener {

	public $rooms = [];

	private $setters, $playerRoom = [];

	public function onEnable () {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->saveDefaultConfig();
		if (!is_dir($this->getDataFolder().'rooms')){
			@mkdir($this->getDataFolder().'rooms');
		}
		$this->loadRooms();
	}

	protected function loadRooms () {
		foreach (glob($this->getDataFolder() . "rooms" . DIRECTORY_SEPARATOR . "*.json") as $roomFile) {
            $config = new Config($roomFile, Config::JSON);
            $this->rooms[basename($roomFile, ".json")] = new Room($this, $config, basename($roomFile, ".json"));
        }
	}

	public function onDisable () {
		foreach ($this->rooms as $room){
			$room->onDisable();
		}
	}

	public function onCommand (CommandSender $sender, Command $cmd, string $label, array $args) : bool {
		if ($cmd->getName() !== 'room'){
			return false;
		}
		if (!$sender instanceof Player){
			$sender->sendMessage('use this command in game!');
			return false;
		}
		if (!$sender->hasPermission($cmd->getPermission())){
			$sender->sendMessage($sender->getServer()->getLanguage()->translateString(TextFormat::RED . "%commands.generic.permission"));
			return false;
		}
		if (!isset($args[0])){
			//$sender->sendMessage($cmd->getUsage());
			return false;
		}
		switch (strtolower($args[0])){
			case 'h':
			case 'help':
				$sender->sendMessage(
					"§cPrivateRoom Commands:\n".
					"§b- help: Displays list of PrivateRoom commands\n".
					"§b- create: Create a new room\n".
					"§b- remove: Remove a room\n".
					"§b- edit: edit a room\n".
					"§b- list: list of rooms"
				);
				break;
			case 'add':
			case 'create':
				if (isset($args[1])){
					if (!isset($this->rooms[strtolower($args[1])])){
						$data = $this->createBaseRoom(strtolower($args[1]));
						$this->rooms[strtolower($args[1])] = new Room($this, $data, strtolower($args[1]));
					}else {
						$sender->sendMessage('§c'.strtolower($args[1]).' already exists!');
					}
				}else {
					$sender->sendMessage('§cUse: /room create <Room name>');
				}
				break;
			case 'delete':
			case 'r':
			case 'remove':
				if (isset($args[1])){
					if (isset($this->rooms[strtolower($args[1])])){
						unset($this->rooms[strtolower($args[1])]);
					}else {
						$sender->sendMessage('§c'.strtolower($args[1]).' not found.');
					}
				}else {
					$sender->sendMessage('§cUse: /room remove <Room name>');
				}
				break;
			case 'edit':
				if (isset($args[1])){
					if (isset($this->rooms[strtolower($args[1])])){
						$room = $this->rooms[strtolower($args[1])];
						if (isset($args[2])){
							switch (strtolower($args[2])){
								case 'world':
								case 'level':
									if (isset($args[3])){
										if ($this->getServer()->getLevelByName($args[3]) !== null){
											$room->data->set('level', $args[3]);
											$sender->sendMessage('§aRoom level update to '.$sender->getLevel()->getFolderName());
										}else {
											$sender->sendMessage('§cThere are no world with name '.$args[3]);
										}
									}else {
										$room->data->set('level', $sender->getLevel()->getFolderName());
										$sender->sendMessage('§aRoom level update to '.$sender->getLevel()->getFolderName());
									}
									break;
								case 'joinsign':
									$this->playerRoom[$sender->getName()] = $room;
									$this->setters[$sender->getName()] = 0;
									$sender->sendMessage('§cBreak Block to set join sign');
									break;
								case 'quitsign':
									$this->playerRoom[$sender->getName()] = $room;
									$this->setters[$sender->getName()] = 1;
									$sender->sendMessage('§cBreak Block to set quit sign');
									break;
								case 'joinposition':
								case 'joinpos':
									$strVector3 = $sender->getX().','.$sender->getY().','.$sender->getZ();
									$room->data->set('joinpos', $strVector3);
									$sender->sendMessage('§aRoom join position update to '.$strVector3); 
									break;
								case 'quitposition':
								case 'quitpos':
									$strVector3 = $sender->getX().','.$sender->getY().','.$sender->getZ();
									$room->data->set('quitpos', $strVector3);
									$sender->sendMessage('§aRoom quit position update to '.$strVector3); 
									break;
								case 'slot':
								case 'slots':
									if (isset($args[3])){
										$number = (int)$args[3];
										$room->data->set('slots', $number);
										$room->reloadSign();
										$sender->sendMessage('§aRoom slots update to '.$number);
									}else {
										$sender->sendMessage('§cUse: /room edit <room name> slots <int>');
									}
									break;
								case 'enable':
									$eror = '';

									if ($room->data->get('quitpos') == null){
										$eror = 'quitpos is not set';
									}
									if ($room->data->get('joinpos') == null){
										$eror = 'joinpos is not set';
									}
									if ($room->data->get('quitsign') == null){
										$eror = 'quitsign is not set';
									}
									if ($room->data->get('joinsign') == null){
										$eror = 'joinsign is not set';
									}
									if ($room->data->get('level') == null){
										$eror = 'level is not set';
									}
									if ($room->isEnabled()){
										$eror = 'Room is already enabled';
									}
									if ($eror === ''){
										$room->setEnabled(true);
										$sender->sendMessage('§aRoom enabled!');
									}else {
										$sender->sendMessage('§cEROR \''.$eror.'\'');
									}
									break;
								default:
									$sender->sendMessage(
										"§cPrivateRoom edit commands\n".
										"§b- level: to set room level\n".
										"§b- joinsign: to set room join sign\n".
										"§b- quitsign: to set room quit sign\n".
										"§b- joinpos: to set room join position\n".
										"§b- quitpos: to set room quit position\n".
										"§b- slots: to set room slots\n".
										"§b- enable: to enable the room\n".
										"§cUse /room edit <Room name> <action> [args...]"
									);
									break;
							}
						}else {
							$sender->sendMessage(
								"§cPrivateRoom edit commands\n".
								"§b- level: to set room level\n".
								"§b- joinsign: to set room join sign\n".
								"§b- quitsign: to set room quit sign\n".
								"§b- joinpos: to set room join position\n".
								"§b- quitpos: to set room quit position\n".
								"§b- slots: to set room slots\n".
								"§b- enable: to enable the room\n".
								"§cUse /room edit <Room name> <action> [args...]"
							);
						}
					}else {
						$sender->sendMessage('§c'.strtolower($args[1]).' not found.');
					}
				}else {
					$sender->sendMessage('§cUse: /room edit <Room name>');
				}
				break;
			case 'list':
				if (count($this->rooms) === 0){
					$sender->sendMessage('§cThere are no rooms yet.');
				}else {
					foreach ($this->rooms as $room) {
						if ($room->isEnabled()){
							$sender->sendMessage('§b- '.$room.' §aEnable');
						}else {
							$sender->sendMessage('§b- '.$room.' §cNot enable');
						}
					}
				}
				break;
			default:
				$sender->sendMessage($cmd->getUsage());
				break;
		}
		return true;
	}

	public function createBaseRoom (string $roomName) {
		$room = new Config($this->getDataFolder().'rooms'.DIRECTORY_SEPARATOR.$roomName.'.json', Config::JSON);
		$data = [
			'level' => '',
			'joinsign' => '',
			'quitsign' => '',
			'joinpos' => '',
			'quitpos' => '',
			'slots' => 2,
			'enable' => false
		];
		$room->setAll($data);
		$room->save();
		return $room;
	}

	public function onBreak (BlockBreakEvent $event) {
		$player = $event->getPlayer();
		$block = $event->getBlock();
		if (isset($this->setters[$player->getName()])){
			$event->setCancelled();
			$room = $this->playerRoom[$player->getName()];
			switch ($this->setters[$player->getName()]){
				case 0:
					$room->data->set('joinsign', $block->getX().','.$block->getY().','.$block->getZ());
					$player->sendMessage('§aRoom join sign update to '.$block->getX().','.$block->getY().','.$block->getZ());
					unset($this->playerRoom[$player->getName()]);
					unset($this->setters[$player->getName()]);
					break;
				case 1:
					$room->data->set('quitsign', $block->getX().','.$block->getY().','.$block->getZ());
					$player->sendMessage('§aRoom quit sign update to '.$block->getX().','.$block->getY().','.$block->getZ());
					unset($this->playerRoom[$player->getName()]);
					unset($this->setters[$player->getName()]);
					break;
			}
		}
	}

	static public function strVector3ToVector3 (string $strVector3) : Vector3 {
		$vector = explode(",", $strVector3);
		return new Vector3((float)$vector[0], (float)$vector[1], (float)$vector[2]);
	}
}