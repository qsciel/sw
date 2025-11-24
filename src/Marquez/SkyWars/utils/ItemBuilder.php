<?php

declare(strict_types=1);

namespace Marquez\SkyWars\utils;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;

/**
 * Fluent builder for creating items with custom NBT
 */
class ItemBuilder {

    private Item $item;
    private ?string $customName = null;
    private array $lore = [];
    private array $nbtTags = [];

    private function __construct(Item $item) {
        $this->item = $item;
    }

    /**
     * Create a new ItemBuilder
     */
    public static function create(Item $item): self {
        return new self($item);
    }

    /**
     * Create from item ID
     */
    public static function fromVanilla(Item $vanillaItem): self {
        return new self(clone $vanillaItem);
    }

    /**
     * Set custom name
     */
    public function setName(string $name): self {
        $this->customName = $name;
        return $this;
    }

    /**
     * Set lore
     * 
     * @param array<string> $lore
     */
    public function setLore(array $lore): self {
        $this->lore = $lore;
        return $this;
    }

    /**
     * Add a line to lore
     */
    public function addLoreLine(string $line): self {
        $this->lore[] = $line;
        return $this;
    }

    /**
     * Set count
     */
    public function setCount(int $count): self {
        $this->item->setCount($count);
        return $this;
    }

    /**
     * Add custom NBT tag (string)
     */
    public function addStringTag(string $key, string $value): self {
        $this->nbtTags[$key] = new StringTag($value);
        return $this;
    }

    /**
     * Add custom NBT compound tag
     */
    public function addCompoundTag(string $key, CompoundTag $tag): self {
        $this->nbtTags[$key] = $tag;
        return $this;
    }

    /**
     * Build and return the item
     */
    public function build(): Item {
        if ($this->customName !== null) {
            $this->item->setCustomName($this->customName);
        }

        if (!empty($this->lore)) {
            $this->item->setLore($this->lore);
        }

        if (!empty($this->nbtTags)) {
            $nbt = $this->item->getNamedTag();
            foreach ($this->nbtTags as $key => $tag) {
                $nbt->setTag($key, $tag);
            }
            $this->item->setNamedTag($nbt);
        }

        return $this->item;
    }
}
