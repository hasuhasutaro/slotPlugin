<?php
namespace noahasu\slot\enum;
enum SlotState {
    case NORMAL; // 通常。777でジャックポットチャンス, 1/10000でフィーバー
    case FEVER; // フィーバー。777でジャックポットチャンス
    case JACKPOT_CHANCE; // ぞろ目でジャックポットへ
    case ULTRA_JACKPOT;
}