<?php

namespace blackjack200\lunar\listener;

use blackjack200\lunar\detection\combat\KillAura;
use blackjack200\lunar\detection\combat\MultiAura;
use blackjack200\lunar\LunarPlayer;
use blackjack200\lunar\user\UserManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\Player;

class DefaultListener implements Listener {
	/** @var LoginPacket[] */
	private array $dirtyLoginPacket = [];

	public function onPlayerPreJoin(PlayerPreLoginEvent $event) : void {


	}

	public function onPlayerJoin(PlayerJoinEvent $event) : void {
		$player = $event->getPlayer();
		UserManager::register($player);
		$hash = spl_object_hash($player);
		$user = UserManager::get($player);
		foreach ($user->getProcessors() as $processor) {
			$processor->processClient($this->dirtyLoginPacket[$hash]);
		}
		unset($this->dirtyLoginPacket[$hash]);
		$user->trigger(KillAura::class, 1, null);
	}

	public function onPlayerQuit(PlayerQuitEvent $event) : void {
		$user = UserManager::get($event->getPlayer());
		$user->close();
		UserManager::unregister($event->getPlayer());
		unset($this->dirtyLoginPacket[spl_object_hash($event->getPlayer())]);
	}

	public function onDataPacketSend(DataPacketSendEvent $event) : void {
		$user = UserManager::get($event->getPlayer());
		if ($user !== null) {
			foreach ($user->getProcessors() as $processor) {
				$processor->processServerBond($event->getPacket());
			}
		}
	}

	public function onDataPacketReceive(DataPacketReceiveEvent $event) : void {
		$packet = $event->getPacket();
		if ($packet instanceof LoginPacket) {
			$this->dirtyLoginPacket[spl_object_hash($event->getPlayer())] = $packet;
		}
		$user = UserManager::get($event->getPlayer());
		if ($user !== null) {
			foreach ($user->getProcessors() as $processor) {
				$processor->processClient($packet);
			}
		}
	}

	public function onEntityDamageByEntity(EntityDamageByEntityEvent $event) : void {
		$damager = $event->getDamager();
		$victim = $event->getEntity();

		if ($damager instanceof Player && $victim instanceof Player) {
			UserManager::get($damager)->trigger(MultiAura::class, $event);
		}
	}
}
