<?php
namespace noahasu\slot;

use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener {

    private static self $instance;

    public static function getInstance() : self {
        return self::$instance;
    }

    protected function onLoad() : void {
        self::$instance = $this;
    }

    public function onEnable() : void {
        $this -> getServer() -> getPluginManager() -> registerEvents($this, $this);
    }

    public function onQuit(PlayerQuitEvent $ev) {
        $player = $ev -> getPlayer();
        SlotHolder::getInstance() -> unregisterFromPlayer($player);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if(!$sender instanceof Player) return true;

        if($label != 'slot' && $label != 'slote') return false;

        if($label == 'slote') {
            if(!SlotHolder::getInstance() -> isRegisterFromPlayer($sender)) {
                $sender -> sendMessage('§bSLOT >> §cスロットを回していません。');
                return true;
            }

            SlotHolder::getInstance() -> unregisterFromPlayer($sender);
            $sender -> sendMessage('§bSLOT >> §cスロットを終了しました。');
            return true;
        }

        if(SlotHolder::getInstance() -> isRegisterFromPlayer($sender)) {
            $sender -> sendMessage('§bSLOT >> §c既にスロットを回しています');
            return false;
        }

        $slot = new Slot($sender);
        $slot -> auto();

        SlotHolder::getInstance() -> register($slot -> getSlotId(), $slot);
        $slot -> slot_first();

        return true;
    }
}