<?php

declare(strict_types=1);

namespace Marquez\SkyWars\manager;

use pocketmine\utils\Config;
use function str_replace;
use function json_decode;
use function is_array;

/**
 * Manages all plugin messages with placeholder support
 */
class MessageManager {

    private array $messages = [];
    private string $prefix;

    public function __construct(
        private Config $config
    ) {
        $this->loadMessages();
    }

    /**
     * Load messages from messages.json
     */
    private function loadMessages(): void {
        $data = json_decode($this->config->getAll(), true);
        
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid messages.json format");
        }

        $this->messages = $data;
        $this->prefix = $data['prefix'] ?? "§7[§bSkyWars§7] ";
    }

    /**
     * Get a message by key path (e.g., "queue.joined")
     * 
     * @param string $key Dot-notation key path
     * @param array<string, string> $placeholders Key-value pairs for replacement
     * @param bool $addPrefix Whether to add the prefix
     * @return string Formatted message
     */
    public function getMessage(string $key, array $placeholders = [], bool $addPrefix = true): string {
        $message = $this->getNestedValue($this->messages, $key);
        
        if ($message === null) {
            return "§cMessage not found: {$key}";
        }

        // Replace placeholders
        foreach ($placeholders as $placeholder => $value) {
            $message = str_replace("{{$placeholder}}", (string)$value, $message);
        }

        return $addPrefix ? $this->prefix . $message : $message;
    }

    /**
     * Get raw message without prefix (useful for items, forms, etc.)
     * 
     * @param string $key Dot-notation key path
     * @param array<string, string> $placeholders Key-value pairs for replacement
     * @return string Formatted message without prefix
     */
    public function getRaw(string $key, array $placeholders = []): string {
        return $this->getMessage($key, $placeholders, false);
    }

    /**
     * Get message as array (for item lore, etc.)
     * 
     * @param string $key Dot-notation key path
     * @param array<string, string> $placeholders Key-value pairs for replacement
     * @return array<string> Array of strings
     */
    public function getArray(string $key, array $placeholders = []): array {
        $value = $this->getNestedValue($this->messages, $key);
        
        if (!is_array($value)) {
            return [$this->getMessage($key, $placeholders, false)];
        }

        $result = [];
        foreach ($value as $line) {
            $processedLine = $line;
            foreach ($placeholders as $placeholder => $val) {
                $processedLine = str_replace("{{$placeholder}}", (string)$val, $processedLine);
            }
            $result[] = $processedLine;
        }

        return $result;
    }

    /**
     * Get the prefix
     * 
     * @return string Message prefix
     */
    public function getPrefix(): string {
        return $this->prefix;
    }

    /**
     * Reload messages from config
     */
    public function reload(): void {
        $this->config->reload();
        $this->loadMessages();
    }

    /**
     * Get nested value from array using dot notation
     * 
     * @param array $array Source array
     * @param string $key Dot-notation key
     * @return mixed|null The value or null if not found
     */
    private function getNestedValue(array $array, string $key): mixed {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }
}
