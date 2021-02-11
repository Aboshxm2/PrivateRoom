<?php 

namespace Aboshxm2\privateroom\object;

use pocketmine\Player;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\level\Position;
use pocketmine\tile\Sign;
use pocketmine\event\player\{PlayerInteractEvent, PlayerCommandPreprocessEvent};
use pocketmine\event\entity\EntityTeleportEvent;
use Aboshxm2\privateroom\_class\PrivateRoom;
use CortexPE\DiscordWebhookAPI\{Message, Webhook};

class Room implements Listener {

	protected $plugin;

	public $data;

	public $name;

	public $players = [];

	public function __construct (PrivateRoom $plugin, Config $data, string $roomName) {
		$this->plugin = $plugin;
		$this->data = $data;
		$this->name = $roomName;
		$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);
		$this->reloadSign();
	}

	public function onSignTouch (PlayerInteractEvent $event) {
		if (!$this->data->get('enable')){
			return;
		}
		$player = $event->getPlayer();
		if (!$this->data->get('level') === $player->getLevel()->getFolderName()){
			return;
		}
		$block = $event->getBlock();
		if ($this->data->get('joinsign') === $block->getX().','.$block->getY().','.$block->getZ()){
			$this->joinToRoom($player);
		}elseif ($this->data->get('quitsign') === $block->getX().','.$block->getY().','.$block->getZ()){
			$this->quitFromRoom($player);
		}
	}

	public function onTeleport (EntityTeleportEvent $event) {
		$player = $event->getEntity();
		if (!$player instanceof Player){
			return;
		}
		if (isset($this->players[$player->getName()])){
			$this->quitFromRoom($player, false);
		}
	}

	public function onChat (PlayerCommandPreprocessEvent $event) {
		if (strpos($event->getMessage(), "/") !== 0){
			$player = $event->getPlayer();
			if (isset($this->players[$player->getName()])){
				$event->setCancelled();
				if ($this->data->exists('webhook')){
					$webHook = new Webhook($this->data->get("webhook"));
					$msg = new Message();
					$msg->setContent(":envelope_with_arrow: ".$player->getName()." > send(\"".$event->getMessage()."\")");
					$webHook->send($msg);
				}
				$this->broadcastMessage(str_replace(['{player}', '{msg}'], [$player->getName(), $event->getMessage()], $this->plugin->getConfig()->get('msgPrefix')));
			}
		}
	}

	public function joinToRoom (Player $player) {
		if (!$this->data->get('enable')){
			$player->sendMessage('§cThe room is not Enable!');
			return;
		}
		if (!$this->data->get('level') === $player->getLevel()->getFolderName()){
			$player->sendMessage('§cYou must to be in same level that sign is in!');
			return;
		}
		if (isset($this->players[$player->getName()])){
			$player->sendMessage('§cYou are in room');
			return;
		}
		if ($this->data->get('slots') <= count($this->players)){
			$player->sendMessage('§cThe room is full!');
			return;
		}
		if ($this->data->exists('webhook')){
			$webHook = new Webhook($this->data->get("webhook"));
			$msg = new Message();
			$msg->setContent(":green_square: join ".$player->getName());
			$webHook->send($msg);
		}
		$vector3 = PrivateRoom::strVector3ToVector3($this->data->get('joinpos'));
		$player->teleport(new Position($vector3->x, $vector3->y, $vector3->z, $player->getServer()->getLevelByName($this->data->get('level'))));
		$this->players[$player->getName()] = $player;
		$this->broadcastMessage(str_replace('{player}', $player->getName(), $this->plugin->getConfig()->get('joinToRoomMsg')));
		$this->reloadSign();
	}

	public function quitFromRoom (Player $player, bool $teleport = true) {
		if ($this->data->exists('webhook')){
			$webHook = new Webhook($this->data->get("webhook"));
			$msg = new Message();
			$msg->setContent(":red_square: quit ".$player->getName());
			$webHook->send($msg);
		}
		$this->broadcastMessage(str_replace('{player}', $player->getName(), $this->plugin->getConfig()->get('quitFromRoomMsg')));
		unset($this->players[$player->getName()]);
		if ($teleport){
			$vector3 = PrivateRoom::strVector3ToVector3($this->data->get('quitpos'));
			$player->teleport(new Position($vector3->x, $vector3->y, $vector3->z, $player->getServer()->getLevelByName($this->data->get('level'))));
		}
		$this->reloadSign();
	}

	public function reloadSign () {
		$sign = $this->plugin->getServer()->getLevelByName($this->data->get('level'))->getTile(PrivateRoom::strVector3ToVector3($this->data->get('joinsign')));
		if ($sign instanceof Sign){
			$text = [];
			foreach ($this->plugin->getConfig()->get('joinSign') as $index => $line){
				$text[$index] = str_replace(["{countPlayers}", "{slots}"], [count($this->players), $this->data->get('slots')], $line);
			}
			$sign->setText($text['0'], $text['1'], $text['2'], $text['3']);
		}
		$sign2 = $this->plugin->getServer()->getLevelByName($this->data->get('level'))->getTile(PrivateRoom::strVector3ToVector3($this->data->get('quitsign')));
		if ($sign2 instanceof Sign){
			$text2 = [];
			foreach ($this->plugin->getConfig()->get('quitSign') as $index => $line){
				$text2[$index] = str_replace(["{countPlayers}", "{slots}"], [count($this->players), $this->data->get('slots')], $line);
			}
			$sign2->setText($text2['0'], $text2['1'], $text2['2'], $text2['3']);
		}
	}

	public function broadcastMessage (string $message) {
		foreach ($this->players as $player){
			$player->sendMessage($message);
		}
	}

	public function isEnabled () {return $this->data->get('enable');}

	public function setEnabled (bool $enabled) {$this->data->set('enable', $enabled);}

	public function onDisable () {
		$this->data->save();
	}

	public function __toString () {return $this->name;}
}