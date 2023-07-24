<?php
namespace noahasu\slot;

use pocketmine\player\Player;

class SlotHolder {
    private static $instance;
    private array $slots = [];
    private array $players = [];

    public static function getInstance() : self {
        if(!isset(self::$instance)) self::$instance = new self;
        return self::$instance;
    }

    public function register(int $slotId, Slot $slot) : void {
        $this -> slots[$slotId] = $slot;
        $this -> players[$slot -> getPlayername()] = $slotId;
    }

    public function unregister(int $slotId) : void {
        if(!$this -> isregister($slotId)) return;
        $playername = $this -> slots[$slotId] -> getPlayername();
        unset($this -> slots[$slotId]);
        unset($this -> players[$playername]);
    }

    public function unregisterFromPlayer(Player $player) {
        if(!isset($this -> players[$player -> getName()])) return;
        $slotId = $this -> players[$player -> getName()];
        $this -> unregister($slotId);
    }

    public function isRegister(int $slotId) : bool {
        return isset($this -> slots[$slotId]);
    }

    public function isRegisterFromPlayer(Player $player) : bool {
        return isset($this -> players[$player -> getName()]);
    }

    public function getSlot(int $slotId) : ?Slot {
        if(!$this -> isregister($slotId)) return null;
        return $this -> slots[$slotId];
    }
}