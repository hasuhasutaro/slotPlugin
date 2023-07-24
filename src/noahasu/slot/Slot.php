<?php
namespace noahasu\slot;

use noahasu\casinoCoin\CasinoCoin;
use pocketmine\player\Player;
use pocketmine\scheduler\TaskHandler;
use pocketmine\Server;
use noahasu\slot\enum\SlotState;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\world\sound\AnvilFallSound;
use pocketmine\world\sound\PopSound;
use pocketmine\world\sound\XpLevelUpSound;

class Slot {
    const SLOT_FEVER = 10;
    const SLOT_NEED_MONEY = 1;

    const BACKGROUND = 0;
    const JPCC_DEFAULT = 1000;
    const TITLE = 1;

    const SOUND_POP = 0;
    const SOUND_HIT = 1;
    const SOUND_FEVER = 2;
    const SOUND_JP = 3;

    private static $slotIdCounter = 0;
    private static $jpCC = self::JPCC_DEFAULT;

    private Player $player;
    private string $playername;
    private int $slotId;
    private SlotState $state;
    private $slotType = self::BACKGROUND;
    private bool $isAuto = false;

    private int $count = 0; // FEVER, JACKPOTCHANCEで使用

    private array $nums = [0, 0, 0];

    public function __construct(Player $player) {
        $this -> player = $player;
        $this -> playername = $player -> getName();
        $this -> slotId = self::$slotIdCounter++;
        $this -> state = SlotState::NORMAL;
    }

    public function getSlotId() : int {
        return $this -> slotId;
    }

    public function getPlayername() : string {
        return $this -> playername;
    } 

    public function auto(bool $bool = true) : void {
        $this -> isAuto = $bool;
    }

    public static function resetJp() : void {
        self::$jpCC = self::JPCC_DEFAULT;
    }

    public function deleteSlot() : void {
        SlotHolder::getInstance() -> unregister($this -> slotId);
    }

    public function slot_start() : void {
        if(!SlotHolder::getInstance() -> isRegister($this -> slotId)) return;
        if(CasinoCoin::getInstance() -> getCasinoCoin($this -> playername) < self::SLOT_NEED_MONEY) {
            $this -> player -> sendMessage('§bSLOT >>§r 所持金が足りません');
            $this -> deleteSlot();
        }

        CasinoCoin::getInstance() -> reduceCasinoCoin($this -> playername, self::SLOT_NEED_MONEY);

        $this -> nums = [0, 0, 0];
        $this -> sendText("スロットを回します…\n現在のJP: ".self::$jpCC);
        ++self::$jpCC;
        Main::getInstance() -> getScheduler() -> scheduleDelayedTask(
            new CallbackTask([$this, "slot_first"]), 10
        );
    }

    public function slot_first() : void {
        $this -> playSound(self::SOUND_POP);
        if(!SlotHolder::getInstance() -> isRegister($this -> slotId)) return;

        $display = 0;

        switch($this -> state) {
            case SlotState::NORMAL:
                $this -> nums[0] = mt_rand(0, 9);
                $display = $this -> nums[0];
                break;
            case SlotState::FEVER:
                if(mt_rand(0,5) == 1) $this -> nums[0] = 7;
                else $this -> nums[0] = mt_rand(0, 9);
                $display = $this -> nums[0];
                break;
            case SlotState::JACKPOT_CHANCE:
                if(mt_rand(0, 4) == 2) {
                    $this -> nums[0] = mt_rand(0, 10);
                    if($this -> nums[0] == 10) {
                        $display = '§l§4U§r';
                        break;
                    }
                }
                else $this -> nums[0] = 7;
                $display = $this -> nums[0];
                break;
            case SlotState::ULTRA_JACKPOT:
                $this -> nums[0] = mt_rand(1, 9);
                $display = '§l§4'.$this -> nums[0].'§r';
            default: $this -> nums[0] = mt_rand(0, 9);
        }

        $msg = '[ '.$display.' ] - [ ? ] - [ ? ]';

        $this -> sendText($this -> createStateText($msg));

        Main::getInstance() -> getScheduler() -> scheduleDelayedTask(
            new CallbackTask([$this, "slot_second"]), 10
        );
    }

    public function slot_second() : void {
        $this -> playSound(self::SOUND_POP);
        if(!SlotHolder::getInstance() -> isRegister($this -> slotId)) return;
        $num = 0;
        $num0Dis = $this -> nums[0];
        $display = 0;

        switch($this -> state) {
            case SlotState::NORMAL:
                $num = mt_rand(0, 9);
                $display = $num;
                break;
            case SlotState::FEVER:
                if(mt_rand(0, 4) != 0) $num = $this -> nums[0];
                else $num = mt_rand(0,9);
                $display = $num;
                break;
            case SlotState::JACKPOT_CHANCE:
                $num = $this -> nums[0];
                $display = $num;
                if($num == 10) {
                    $num0Dis = '§l§4U§r';
                    $display = '§l§4J§r';
                }
                break;
            case SlotState::ULTRA_JACKPOT:
                $num = $this -> nums[0];
                $num0Dis = '§l§4'.$this -> nums[0].'§r';
                $display = '§l§4'.$num.'§r';
                break;
        }
        
        $this -> nums[1] = $num;
        $msg = '[ '.$num0Dis.' ] - [ '.$display.' ] - [ ? ]';
        $this -> sendText($this -> createStateText($msg));

        Main::getInstance() -> getScheduler() -> scheduleDelayedTask(
            new CallbackTask([$this, "slot_third"]), 10
        );
    }

    public function slot_third() : void {
        $this -> playSound(self::SOUND_POP);
        if(!SlotHolder::getInstance() -> isRegister($this -> slotId)) return;
        $this -> nums[2] = mt_Rand(0, 9);
        $msg = '[ '.$this -> nums[0].' ] - [ '.$this -> nums[1].' ] - [ '.$this -> nums[2].' ]';

        if($this -> state == SlotState::JACKPOT_CHANCE && $this -> nums[0] == 10) {
            if(mt_rand(0, 2) == 2) {
                $this -> nums[2] = 10;
                $msg = '[ §l§4U§r ] - [ §l§4J§r ] - [ §l§4P§r ]';
            }
        } else if($this -> state == SlotState::ULTRA_JACKPOT) {
            $this -> nums[2] = $this -> nums[0];
            $msg = '[ §l§4'.$this -> nums[0].'§r ] - [ §l§4'.$this -> nums[0].'§r ] - [ §l§4'.$this -> nums[0].'§r ]';
        }
        
        $this -> sendText($this -> createStateText($msg));

        Main::getInstance() -> getScheduler() -> scheduleDelayedTask(
            new CallbackTask([$this, "slot_end"]), 10
        );
    }

    public function slot_end() : void {
        if(!SlotHolder::getInstance() -> isRegister($this -> slotId)) return;
        if($this -> state != SlotState::NORMAL) {
            if(--$this -> count < 0) {
                $this -> state = SlotState::NORMAL;
                $this -> player -> sendMessage('§bSLOT >> 通常状態に戻りました。');
            }
        }

        // [1] - [2] - [3] などの連番
        if($this -> nums[1] == $this -> nums[0] + 1 && $this -> nums[2] == $this -> nums[0] + 2) {
            $this -> playSound(self::SOUND_HIT);
            $returnCC = self::SLOT_NEED_MONEY * 5;
            CasinoCoin::getInstance() -> addCasinoCoin($this -> playername, $returnCC);
            $this -> player -> sendMessage("§bSLOT >> §r連番ボーナス！\n§bSLOT >> §r".$returnCC.CasinoCoin::CC_UNIT.'取得しました！');
            
            if(!$this -> isAuto) return;

            Main::getInstance() -> getScheduler() -> scheduleDelayedTask(
                new CallbackTask([$this, "slot_start"]), 10
            );
            return;
        }

        // そろわなかった
        if($this -> nums[0] != $this -> nums[1] || $this -> nums[1] != $this -> nums[2]) {
            if($this -> state == SlotState::NORMAL && mt_rand(0, self::SLOT_FEVER - 1) == 0) {
                $this -> state = SlotState::FEVER;
                $this -> playSound(self::SOUND_FEVER);
                $this -> player -> sendMessage('§b->->-< §aF§bE§cV§dE§eR §b >-<-<-');
                $this -> count = mt_rand(10, 25);
            }

            if(!$this -> isAuto) return;

            Main::getInstance() -> getScheduler() -> scheduleDelayedTask(
                new CallbackTask([$this, "slot_start"]), 5
            );
            return;
        }

        

        if($this -> state == SlotState::ULTRA_JACKPOT) {
            $this -> playSound(self::SOUND_JP);
            $name = $this -> playername;

            $cc = self::$jpCC * $this -> nums[0];
            self::resetJp();
            $this -> player -> getServer() -> broadcastMessage(
                '§bSLOT >> §l§4'.$name."がウルトラジャックポット！\n§bSLOT >> §l§4".$this -> nums[0].'倍！'.$cc.CasinoCoin::CC_UNIT.'をゲット！！'
            );

            CasinoCoin::getInstance() -> addCasinoCoin($this -> playername, $cc);
            $this -> player -> sendMessage("§bSLOT >> §rウルトラジャックポット当選！\n§bSLOT >> §r".$cc.CasinoCoin::CC_UNIT.'取得しました！');
            $this -> deleteSlot();
            return;
        }

        // JACKPOT_CHANCEでUJPを引いた時
        if($this -> nums[0] == 10) {
            $this -> state == SlotState::ULTRA_JACKPOT;
            $this -> playSound(self::SOUND_JP);
            $this -> sendText($this -> createStateText('[ §l§4§ka§r ] - [ §l§4§kb§r ] - [ §l§4§kc§r ]'));
            Main::getInstance() -> getScheduler() -> scheduleDelayedTask(
                new CallbackTask([$this, "slot_first"]), 15
            );
        }
        
        // 7のぞろ目
        if($this -> nums[0] == 7) {
            if($this -> state == SlotState::JACKPOT_CHANCE) {
                $this -> playSound(self::SOUND_JP);
                $name = $this -> playername;

                $cc = self::$jpCC;
                self::resetJp();
                $this -> player -> getServer() -> broadcastMessage(
                    '§bSLOT >> §e'.$name."がジャックポット！\n§bSLOT >> §e".$cc.CasinoCoin::CC_UNIT.'をゲット！！'
                );

                CasinoCoin::getInstance() -> addCasinoCoin($this -> playername, $cc);
                $this -> player -> sendMessage("§bSLOT >> §rジャックポット当選！\n§bSLOT >> §r".$cc.CasinoCoin::CC_UNIT.'取得しました！');
                $this -> deleteSlot();
                return;
            }

            $this -> state = SlotState::JACKPOT_CHANCE;
            $this -> playSound(self::SOUND_FEVER);
            $this -> player -> sendMessage('§b->->-< §l§o§4 JACKPOT CHANCE §b >-<-<-');
            $this -> count = mt_rand(10, 15);
        } else { // 普通のぞろ目
            $this -> playSound(self::SOUND_HIT);
            $returnCC = self::SLOT_NEED_MONEY * 10;
            CasinoCoin::getInstance() -> addCasinoCoin($this -> playername, $returnCC);
            $this -> player -> sendMessage("§bSLOT >> §rぞろ目当選！\n§bSLOT >> §r".$returnCC.CasinoCoin::CC_UNIT.'取得しました！');
        }

        if(!$this -> isAuto) return;

        Main::getInstance() -> getScheduler() -> scheduleDelayedTask(
            new CallbackTask([$this, "slot_start"]), 5
        );
    }

    public function createStateText(string $msg) : string {
        switch($this -> state) {
            case SlotState::NORMAL:
                return $msg;
            case SlotState::FEVER:
                $msg = "§b->->-< §l§aF§bE§cV§dE§eR§r : ".$this -> count." §b >-<-<-\n    ".$msg;
                return $msg;
            case SlotState::JACKPOT_CHANCE:
                $msg = "§b->->-< §l§o§c JACKPOT CHANCE §r: ".$this -> count."§b >-<-<-\n          ".$msg;
                return $msg;
            case SlotState::ULTRA_JACKPOT:
                $msg = "§b->->-< §l§o§4 ULTRA JACKPOT§b >-<-<-\n    ".$msg;
                return $msg;
        }
    }

    public function sendText(string $msg) {
        switch($this -> slotType) {
            case self::BACKGROUND:
                $this -> player -> sendTip($msg);
                break;
            case self::TITLE:
                $this -> player -> sendTitle($msg);
        }
    }

    public function playSound($type) {
        if(!$this -> player -> isConnected()) return;
        switch($type) {
            case self::SOUND_POP:
                $this -> player -> getNetworkSession() -> sendDataPacket(PlaySoundPacket::create('random.pop', $this -> player -> getPosition() -> x, $this -> player -> getPosition() -> y, $this -> player -> getPosition() -> z, 0.5, 1));
                break;
            case self::SOUND_HIT:
                $this -> player -> getNetworkSession() -> sendDataPacket(PlaySoundPacket::create('random.levelup', $this -> player -> getPosition() -> x, $this -> player -> getPosition() -> y, $this -> player -> getPosition() -> z, 0.5, 1));
                break;
            case self::SOUND_FEVER:
                $this -> player -> getNetworkSession() -> sendDataPacket(PlaySoundPacket::create('ambient.weather.thunder', $this -> player -> getPosition() -> x, $this -> player -> getPosition() -> y, $this -> player -> getPosition() -> z, 0.5, 1));
                break;
            case self::SOUND_JP:
                $this -> player -> getNetworkSession() -> sendDataPacket(PlaySoundPacket::create('random.explode', $this -> player -> getPosition() -> x, $this -> player -> getPosition() -> y, $this -> player -> getPosition() -> z, 0.5, 1));
                break;
        }
    }
}