<?php
declare(strict_types=1);

namespace twisted\autoclearlagg;

use pocketmine\entity\Creature;
use pocketmine\entity\Human;
use pocketmine\entity\object\ExperienceOrb;
use pocketmine\entity\object\ItemEntity;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\BinaryStream;
use function array_map;
use function in_array;
use function is_array;
use function is_numeric;
use function str_replace;
use function strtolower;

class AutoClearLagg extends PluginBase
{

    public const LANG_TIME_LEFT = "time-left";
    public const LANG_ENTITIES_CLEARED = "entities-cleared";
    public const TYPE_CHAT = 1;
    public const TYPE_POPUP = 2;
    public const TYPE_TITLE = 3;

    /** @var int */
    private int $interval;
    /** @var int */
    private int $seconds;

    /** @var bool */
    private bool $clearItems;
    /** @var bool */
    private bool $clearMobs;
    /** @var bool */
    private bool $clearXpOrbs;

    /** @var string[] */
    private array $exemptEntities;

    /** @var string[] */
    private array $messages;
    /** @var int[] */
    private array $broadcastTimes;
    /** @var int */
    private int $messageType = self::TYPE_CHAT;
    /** @var string */
    private string $soundTimeLeft = "off";
    /** @var string */
    private string $soundCleared = "off";

    public function onEnable(): void
    {
        $config = $this->getConfig()->getAll();

        if (!is_numeric($config["seconds"] ?? 300)) {

            $this->getLogger()->error("Config error: seconds attribute must an integer");
            $this->getServer()->getPluginManager()->disablePlugin($this);

            return;
        }
        $this->interval = $this->seconds = (int)$config["seconds"];

        if (!is_array($config["clear"] ?? [])) {

            $this->getLogger()->error("Config error: clear attribute must an array");
            $this->getServer()->getPluginManager()->disablePlugin($this);

            return;
        }
        $clear = $config["clear"] ?? [];
        $this->clearItems = (bool) ($clear["items"] ?? false);
        $this->clearMobs = (bool) ($clear["mobs"] ?? false);
        $this->clearXpOrbs = (bool) ($clear["xp-orbs"] ?? false);

        if (!is_array($clear["exempt"] ?? [])) {

            $this->getLogger()->error("Config error: clear.exempt attribute must an array");
            $this->getServer()->getPluginManager()->disablePlugin($this);

            return;
        }
        $this->exemptEntities = array_map(function($entity) : string{
            return strtolower((string) $entity);
        }, $clear["exempt"] ?? []);

        if (!is_array($config["messages"] ?? [])) {

            $this->getLogger()->error("Config error: times attribute must an array");
            $this->getServer()->getPluginManager()->disablePlugin($this);

            return;
        }

        $messages = $config["messages"] ?? [];
        $this->messages = [
            self::LANG_TIME_LEFT => $messages[self::LANG_TIME_LEFT] ?? "§cEntities will clear in {SECONDS} seconds",
            self::LANG_ENTITIES_CLEARED => $messages[self::LANG_ENTITIES_CLEARED] ?? "§cCleared a total of {COUNT} entities"
        ];

        if(!is_array($config["times"] ?? [])){
            $this->getLogger()->error("Config error: times attribute must an array");
            $this->getServer()->getPluginManager()->disablePlugin($this);

            return;
        }
        $this->broadcastTimes = $config["times"] ?? [60, 30, 15, 10, 5, 4, 3, 2, 1];

        $messageType = match ($config["message-type"]) {
            default => self::TYPE_CHAT,
            "popup" => self::TYPE_POPUP,
            "title" => self::TYPE_TITLE
        };

        $this->messageType = $messageType;

        if(isset($config["sound-time-left"]) && isset($config["sound-cleared"])){
            $this->soundTimeLeft = $config["sound-time-left"];
            $this->soundCleared = $config["sound-cleared"];
        }


        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{

            if (--$this->seconds === 0) {

                $entitiesCleared = 0;

                foreach ($this->getServer()->getLevels() as $level) {
                    foreach ($level->getEntities() as $entity) {

                        if ($this->clearItems && $entity instanceof ItemEntity) {

                            $entity->flagForDespawn();
                            ++$entitiesCleared;

                        } elseif ($this->clearMobs && $entity instanceof Creature && !$entity instanceof Human) {

                            if (!in_array(strtolower($entity->getName()), $this->exemptEntities)) {
                                $entity->flagForDespawn();
                                ++$entitiesCleared;
                            }

                        } elseif ($this->clearXpOrbs && $entity instanceof ExperienceOrb) {

                            $entity->flagForDespawn();
                            ++$entitiesCleared;

                        }

                    }
                }

                if ($this->messages[self::LANG_ENTITIES_CLEARED] != "") {

                    if ($this->soundCleared != "off") {
                        $this->broadcastSound($this->soundCleared);
                    }

                    $format = str_replace("{COUNT}", (string)$entitiesCleared, $this->messages[self::LANG_ENTITIES_CLEARED]);

                    switch ($this->messageType){
                        case self::TYPE_POPUP:
                            $this->broadcastPopup($format);
                            break;
                        case self::TYPE_TITLE:
                            $this->broadcastTitle($format);
                            break;
                        default:
                            $this->getServer()->broadcastMessage($format);
                            break;
                    }

                }

                $this->seconds = $this->interval;

            } elseif (in_array($this->seconds, $this->broadcastTimes) && $this->messages[self::LANG_TIME_LEFT] != ""){

                if ($this->soundTimeLeft != "off") {
                    $this->broadcastSound($this->soundTimeLeft);
                }

                $format = str_replace("{SECONDS}", (string)$this->seconds, $this->messages[self::LANG_TIME_LEFT]);

                switch ($this->messageType){
                    case self::TYPE_POPUP:
                        $this->broadcastPopup($format);
                        break;
                    case self::TYPE_TITLE:
                        $this->broadcastTitle($format);
                        break;
                    default:
                        $this->getServer()->broadcastMessage($format);
                        break;
                }

            }

        }), 20);
    }

    /**
     * Broadcasts a given sound id to all players on the server
     * @param string $id
     */
    public function broadcastSound(string $id): void
    {
        $onlinePlayers = Server::getInstance()->getOnlinePlayers();
        foreach ($onlinePlayers as $player) {

            $pk = new PlaySoundPacket();
            $pk->soundName = $id;
            $pk->pitch = 1;
            $pk->volume = 1;

            $pk->x = $player->x;
            $pk->y = $player->y;
            $pk->z = $player->z;

            $player->sendDataPacket($pk);

        }
    }

    /**
     * Broadcasts a given string to all players on the server in a popup
     * @param string $message
     */
    public function broadcastPopup(string $message): void
    {
        $onlinePlayers = Server::getInstance()->getOnlinePlayers();
        foreach ($onlinePlayers as $player) {

            $player->sendPopup($message);

        }
    }

    /**
     * Broadcasts a given string to all players on the server in a title
     * @param string $message
     */
    public function broadcastTitle(string $message): void
    {
        $onlinePlayers = Server::getInstance()->getOnlinePlayers();
        foreach ($onlinePlayers as $player) {

            $player->addTitle($message);

        }
    }

}
