<?php

/*
Copyright 2021. 동동 all rights reserved.

문의: kakao.dongdong1234451.kro.kr 또는 dongdong1234451@gmail.com

본 플러그인은 "GNU General Public License v3.0"에 의해 보호받습니다.
 */

namespace JUN;

use FormSystem\form\ButtonForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\event\entity\EntityInventoryChangeEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\level\Level;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\StopSoundPacket;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\Config;

class BackGroundMusic extends PluginBase implements Listener{

    public $db, $database, $playermusic = [], $task = [];
    private $prefix;

    public function onDisable(){
        $this->save();
    }

    public function save(){
        $this->database->setAll($this->db);
        $this->database->save();
    }

    public function onEnable(){
        $this->database = new Config($this->getDataFolder() . 'data.yml', Config::YAML, [
            "musiclist" => [],
            "prefix" => "§b§l[BGM] §r§7"
        ]);
        $this->db = $this->database->getAll();

            $this->prefix = $this->db['prefix'];
            date_default_timezone_set('Asia/Seoul');

            $cmd = new PluginCommand ('bgm', $this);
            $cmd->setDescription('bgm을 관리합니다');
            $cmd->setPermission('op');
            $this->getServer()->getCommandMap()->register($this->getDescription()->getName(), $cmd);
            $cmd2 = new PluginCommand ('bgm 정보', $this);
            $cmd2->setDescription('bgm 정보');

            $this->getServer()->getCommandMap()->register($this->getDescription()->getName(), $cmd2);

            $this->getServer()->getPluginManager()->registerEvents($this, $this);
        }
    }

    public function teleport(EntityTeleportEvent $ev){
        $player = $ev->getEntity();
        if($player instanceof \pocketmine\Player){
            if($ev->getFrom()->getLevel() !== $ev->getTo()->getLevel()){
                if(isset($this->db['musiclist'][$ev->getTo()->getLevel()->getFolderName()])){
                    $this->Lplaymusic($player, $this->db['musiclist'][$ev->getTo()->getLevel()->getFolderName()], $ev->getTo()->getLevel());
                }
            }
        }
    }

    public function onJoin(PlayerJoinEvent $ev){
        if(!isset($this->db[$ev->getPlayer()->getName()])){
            if(isset($this->db['musiclist'][$ev->getPlayer()->getLevel()->getFolderName()])){
                $this->playmusic($ev->getPlayer(), $this->db['musiclist'][$ev->getPlayer()->getLevel()->getFolderName()]);
            }
        }
    }

    public function playmusic(\pocketmine\Player $player, array $musicname){
        if(!isset($this->db['toggle'][$player->getLevel()->getFolderName()])){
            $this->db['toggle'][$player->getLevel()->getFolderName()] = true;
        }
        if($this->db['toggle'][$player->getLevel()->getFolderName()]){
            if(isset($this->playermusic[$player->getName()])){
                $this->stopSound($player);
            }
            if(isset($this->task[$player->getName()])){
                $this->getScheduler()->cancelTask($this->task[$player->getName()]->getTaskId());
            }
            $ex = explode("&^&", $musicname[0]);
            $task = new MusicSoundTask($player, $ex[0], 0, $this, $player->getLevel()->getFolderName());
            $this->playermusic[$player->getName()] = $ex[0];
            $this->task[$player->getName()] = $this->getScheduler()->scheduleRepeatingTask($task, 20 * (int) $ex[1]);
        }
    }

    public function Lplaymusic(\pocketmine\Player $player, array $musicname, Level $level){
        if(!isset($this->db['toggle'][$level->getFolderName()])){
            $this->db['toggle'][$player->getLevel()->getFolderName()] = true;
        }
        if($this->db['toggle'][$level->getFolderName()]){
            if(isset($this->playermusic[$player->getName()])){
                $this->stopSound($player);
            }
            if(isset($this->task[$player->getName()])){
                $this->getScheduler()->cancelTask($this->task[$player->getName()]->getTaskId());
            }
            $ex = explode("&^&", $musicname[0]);
            $task = new MusicSoundTask($player, $ex[0], 0, $this, $level->getFolderName());
            $this->playermusic[$player->getName()] = $ex[0];
            $this->task[$player->getName()] = $this->getScheduler()->scheduleRepeatingTask($task, 20 * (int) $ex[1]);
        }
    }

    public function stopSound(\pocketmine\Player $player){
        if(isset($this->playermusic[$player->getName()])){
            $packet = new StopSoundPacket();
            $packet->soundName = $this->playermusic[$player->getName()];
            $packet->stopAll = true;
            $this->playermusic[$player->getName()] = null;
            $player->dataPacket($packet);
        }
    }

    public function form(\pocketmine\Player $player){
        $form = new ButtonForm(function($player, $data){
            /** @var \pocketmine\Player $player */
            if($data === null)
                return true;
            if($data === 0){ //add
                $form = new \FormSystem\form\CustomForm(function($player, $data){
                    /** @var \pocketmine\Player $player */
                    if(!isset($data[0]) and !isset($data[1]) and !isset($data[2]))
                        return true;

                    Server::getInstance()->dispatchCommand($player, "bgm add {$data[0]} {$data[1]}&^&{$data[2]}");
                    return true;
                });
                $form->setTitle("§lBGM");
                $form->addInput("월드 이름을 적어주세요");
                $form->addInput("노래 이름을 적어주세요", null, "jun.plugins.spawnmusic");
                $form->addInput("노래 시간을 적어주세요 (초)", null, "60");

                $player->sendForm($form);
                return true;
            }
            if($data === 1){ //remove
                $form = new \FormSystem\form\CustomForm(function($player, $data){
                    /** @var \pocketmine\Player $player */
                    if(!isset($data[0]) and !isset($data[1]))
                        return true;

                    Server::getInstance()->dispatchCommand($player, "bgm remove {$data[0]} {$data[1]}");
                    return true;
                });
                $form->setTitle("§lBGM");
                $form->addInput("월드 이름을 적어주세요");
                $form->addInput("노래 이름을 적어주세요", null, "jun.plugins.spawnmusic");

                $player->sendForm($form);
                return true;
            }
            if($data === 2){ //list
                $form = new \FormSystem\form\CustomForm(function($player, $data){
                    /** @var \pocketmine\Player $player */
                    if(!isset($data[0]))
                        return true;

                    Server::getInstance()->dispatchCommand($player, "bgm list {$data[0]}");
                    return true;
                });
                $form->setTitle("§lBGM");
                $form->addInput("월드 이름을 적어주세요");

                $player->sendForm($form);
                return true;
            }
            if($data === 3){ //on
                $form = new \FormSystem\form\CustomForm(function($player, $data){
                    /** @var \pocketmine\Player $player */
                    if(!isset($data[0]))
                        return true;

                    Server::getInstance()->dispatchCommand($player, "bgm on {$data[0]}");
                    return true;
                });
                $form->setTitle("§lBGM");
                $form->addInput("월드 이름을 적어주세요 or @a");

                $player->sendForm($form);
                return true;
            }
            if($data === 4){ //off
                $form = new \FormSystem\form\CustomForm(function($player, $data){
                    /** @var \pocketmine\Player $player */
                    if(!isset($data[0]))
                        return true;

                    Server::getInstance()->dispatchCommand($player, "bgm off {$data[0]}");
                    return true;
                });
                $form->setTitle("§lBGM");
                $form->addInput("월드 이름을 적어주세요 or @a");

                $player->sendForm($form);
                return true;
            }
            return true;
        });
        $form->setTitle("§lBGM");
        $form->setContent("원하시는 작업을 선택해주세요");

        $form->addButton("§ladd\n§r§8월드별 재생목록에 노래를 추가합니다");
        $form->addButton("§lremove\n§r§8월드별 재생목록에서 노래를 제거합니다");
        $form->addButton("§llist\n§r§8월드별 재생목록에서 노래를 확인합니다");
        $form->addButton("§lon\n§r§8전체적으로 노래를 재생합니다");
        $form->addButton("§loff\n§r§8전체적으로 노래를 재생합니다");

        $player->sendForm($form);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if($command->getName() === "bgm 정보"){
            $sender->sendMessage(" 플러그인 제작 : JUN-KR\n 깃허브 : https://github.com/JUN-KR");
            return true;
        }
        if($command->getName() === 'bgm'){
            if(isset($args[0]) and isset($args[1]) and isset($args[2])){
                $subcommand = $args[0];
                switch($subcommand){
                    case "add":
                        //1 월드이름 2 노래
                        if($this->getServer()->getLevelByName($args[1]) !== null){
                            $this->db['musiclist'][$args[1]][] = $args[2];
                            $sender->sendMessage($this->prefix . '§aBGM이 성공적으로 추가되었습니다.');
                        }else{
                            $sender->sendMessage($this->prefix . '해당 월드를 찾을 수 없습니다 §4BGM 추가에 실패했습니다.');
                        }
                        break;
                    case "remove":
                        //월드가 삭제되어 노래를 제거 할 수 있으니, 검사X
                        if(!isset($this->db['musiclist'][$args[1]])){
                            $sender->sendMessage($this->prefix . '해당 월드는 음악에 등록되어있지 않습니다');
                            break;
                        }

                        foreach($this->db['musiclist'][$args[1]] as $num => $music){
                            if(strpos($music, $args[2]) !== false){
                                var_dump($music);
                                var_dump($num);
                                $this->db['musiclist'][$args[1]] = array_diff($this->db['musiclist'][$args[1]], array_splice($this->db['musiclist'][$args[1]], $num, 1));

                                $i = 0;
                                foreach($this->db['musiclist'][$args[1]] as $key => $val){
                                    unset($this->db['musiclist'][$args[1]][$key]);

                                    $new_key = $i;
                                    $this->db['musiclist'][$args[1]][$new_key] = $val;

                                    $i++;
                                }

                                $sender->sendMessage($this->prefix . "§aBGM이 성공적으로 제거되었습니다");
                                return true;
                                break;
                            }
                        }
                        $sender->sendMessage($this->prefix . $args[2] . ' 음악이 ' . $args[1] . '월드에 등록되어있지 않습니다. §4BGM 제거에 실패했습니다.');
                        break;
                }
            }elseif(isset($args[0]) && isset($args[1])){
                switch($args[0]){
                    case "list":
                        if(!isset($this->db['musiclist'][$args[1]])){
                            $sender->sendMessage($this->prefix . '해당 월드에 BGM이 등록되어있지 않습니다');
                            break;
                        }
                        foreach($this->db['musiclist'][$args[1]] as $musiclist){
                            $ex = explode("&^&", $musiclist);
                            $sender->sendMessage("노래제목 : " . $ex[0] . ' ' . $ex[1] . "초");
                        }

                        break;
                    case "on":
                        if($args[1] === '@a'){
                            foreach($this->db['musiclist'] as $world => $worldvalue){
                                $this->db['toggle'][$world] = true;
                                $this->worldstart($args[1]);
                            }
                            $sender->sendMessage($this->prefix . "§a브금이 성공적으로 켜졌습니다.");
                            break;
                        }
                        $this->db['toggle'][$args[1]] = true;
                        $sender->sendMessage($this->prefix . "§a브금이 성공적으로 켜졌습니다.");
                        $this->worldstart($args[1]);
                        break;
                    case "off":
                        if($args[1] === '@a'){
                            foreach($this->db['musiclist'] as $world => $worldasdf){
                                $this->db['toggle'][$world] = false;
                            }
                            $sender->sendMessage($this->prefix . "§a브금이 성공적으로 꺼졌습니다.");
                            foreach(Server::getInstance()->getOnlinePlayers() as $onlinePlayer){
                                if(isset($this->task[$sender->getName()])){
                                    $this->getScheduler()->cancelTask($this->task[$sender->getName()]->getTaskId());
                                    unset($this->task[$sender->getName()]);
                                }
                                $this->stopSound($onlinePlayer);
                            }
                            break;
                        }
                        $this->worldstop($args[1]);
                        $this->db['toggle'][$args[1]] = false;
                        $sender->sendMessage($this->prefix . "§a브금이 성공적으로 꺼졌습니다.");
                        break;
                }
            }else{
                $this->form($sender);
            }
        }
        return true;
    }

    public function worldstart($worldname){
        if($worldname === "@a"){
            foreach(Server::getInstance()->getOnlinePlayers() as $onlinePlayer){
                if(isset($this->db['musiclist'][$onlinePlayer->getLevel()->getFolderName()])){
                    $this->playmusic($onlinePlayer, $this->db['musiclist'][$onlinePlayer->getLevel()->getFolderName()]);
                }
            }
            return true;
        }
        foreach(Server::getInstance()->getOnlinePlayers() as $onlinePlayer){
            if($onlinePlayer->getLevel()->getFolderName() === $worldname){
                $this->playmusic($onlinePlayer, $this->db['musiclist'][$worldname]);
            }
        }
    }

    public function worldstop($worldname){
        foreach(Server::getInstance()->getOnlinePlayers() as $onlinePlayer){
            if($onlinePlayer->getLevel()->getFolderName() === $worldname){
                if(isset($this->task[$onlinePlayer->getName()])){
                    $this->getScheduler()->cancelTask($this->task[$onlinePlayer->getName()]->getTaskId());
                    unset($this->task[$onlinePlayer->getName()]);
                }
                $this->stopSound($onlinePlayer);
            }
        }
    }

    function getnextmusic(MusicSoundTask $task, $worldname, $id, \pocketmine\Player $player){
        $this->stopSound($player);
        $this->getScheduler()->cancelTask($task->getTaskId());
        if(!isset($this->db['musiclist'][$worldname])){
            return true;
        }
        if(!isset($this->db['musiclist'][$worldname][$id + 1])){
            $ex = explode("&^&", $this->db['musiclist'][$worldname][0]);
            $this->playermusic[$player->getName()] = $ex[0];
            if($player->isOnline()){
                $task = new MusicSoundTask($player, $ex[0], 0, $this, $player->getLevel()->getFolderName());
                $this->task[$player->getName()] = $this->getScheduler()->scheduleRepeatingTask($task, 20 * (int) $ex[1]);
            }
            return true;
        }
        $ex = explode("&^&", $this->db['musiclist'][$worldname][$id + 1]);
        $this->playermusic[$player->getName()] = $ex[0];
        if($player->isOnline()){
            #$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function(int $currentTick) use ($player, $ex, $id) : void{
            $task = new MusicSoundTask($player, $ex[0], $id + 1, $this, $player->getLevel()->getFolderName());
            $this->task[$player->getName()] = $this->getScheduler()->scheduleRepeatingTask($task, 20 * (int) $ex[1]);
            #}), 10);
        }
        return true;
    }
}

class MusicSoundTask extends Task{
    private $player, $musiclist, $count, $id, $owner, $worldname;

    public function __construct($player, $musiclist, $id, $owner, $worldname){
        $this->player = $player;
        $this->musiclist = $musiclist;
        $this->count = 0;
        $this->id = $id;
        $this->owner = $owner;
        $this->worldname = $worldname;
    }

    public function onRun(int $currentTick){
        if($this->count === 1){
            $this->owner->getnextmusic($this, $this->worldname, $this->id, $this->player);
            return;
        }
        $this->playsound($this->player, $this->musiclist);
        $this->count++;
    }

    public function playsound($p, $soundname){
        $packet = new PlaySoundPacket();
        $packet->soundName = $soundname;
        $packet->x = $p->getX();
        $packet->y = $p->getY();
        $packet->z = $p->getZ();
        $packet->volume = 100;
        $packet->pitch = 1;
        $p->dataPacket($packet);
    }
}
