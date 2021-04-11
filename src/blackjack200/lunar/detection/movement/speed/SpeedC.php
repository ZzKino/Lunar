<?php


namespace blackjack200\lunar\detection\movement\speed;


use blackjack200\lunar\detection\DetectionBase;
use blackjack200\lunar\user\User;
use pocketmine\entity\Effect;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;

class SpeedC extends DetectionBase {
	protected float $reward;
	protected float $lastLostSpeed = 0.0;

	public function __construct(User $user, string $name, $data) {
		parent::__construct($user, $name, $data);
		$this->reward = $this->getConfiguration()->getReward();
		$this->lastLostSpeed = microtime(true);
	}

	public function handleClient(DataPacket $packet) : void {
		if ($packet instanceof MovePlayerPacket) {
			$user = $this->getUser();
			$m = $user->getMovementInfo();
			$deltaXZ = hypot($m->moveDelta->x, $m->moveDelta->z);
			$maxSpeed = $this->getSpeed(0.4);
			$t = microtime(true) - $this->lastLostSpeed;
			if ($deltaXZ > $maxSpeed &&
				$t > 1 &&
				!$user->getMovementInfo()->onIce &&
				$user->getMovementInfo()->timeSinceTeleport() >= 0.25 &&
				$user->getMovementInfo()->timeSinceMotion() >= 0.75 &&
				!$user->getPlayer()->isCreative(true) &&
				!$user->getPlayer()->isFlying()
			) {
				if (++$this->preVL > 3) {
					$this->addVL(1);
					$this->preVL = 0;
					$this->suppress();
					if ($this->overflowVL()) {
						$this->fail("A=$deltaXZ E=$maxSpeed");
					}
				}
			} elseif ($deltaXZ > 0) {
				$this->rewardPreVL($this->reward);
			}
		}
	}

	private function getSpeed(float $speed) : float {
		$speed *= 1 + (0.2 * $this->getSpeedLevel());
		return $speed;
	}

	public function getSpeedLevel() : int {
		$effect = $this->getUser()->getPlayer()->getEffect(Effect::SPEED);
		if ($effect !== null) {
			return $effect->getEffectLevel();
		}
		$this->lastLostSpeed = microtime(true);
		return 0;
	}
}