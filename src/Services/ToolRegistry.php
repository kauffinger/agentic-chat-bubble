<?php

namespace Kauffinger\AgenticChatBubble\Services;

use InvalidArgumentException;
use Prism\Prism\Tool;

class ToolRegistry
{
    private array $tools = [];

    /**
     * Register a tool
     */
    public function register(mixed $tool): void
    {
        $this->tools[] = $tool;
    }

    /**
     * Register multiple tools at once
     */
    public function registerMany(array $tools): void
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    /**
     * Resolve tools from mixed array without registering them
     */
    public function resolveTools(array $tools): array
    {
        $resolvedTools = [];

        foreach ($tools as $tool) {
            $resolvedTools[] = $this->resolveTool($tool);
        }

        return $resolvedTools;
    }

    /**
     * Get all tools (config + dynamic) merged together
     */
    public function getAllTools(): array
    {
        // Get tools from config and resolve them fresh each time (don't persist them)
        $configuredTools = \Statamic\Facades\Config::get('agentic-chat-bubble.tools', []);
        $resolvedConfigTools = $this->resolveTools($configuredTools);

        // Get persisted dynamic tools
        $dynamicTools = $this->all();

        return array_merge($resolvedConfigTools, $dynamicTools);
    }

    /**
     * Resolve a single tool to a Tool instance
     */
    private function resolveTool(mixed $tool): Tool
    {
        if (is_string($tool)) {
            // Class string - resolve from container
            return app($tool);
        } elseif (is_callable($tool)) {
            // Closure or callable - invoke it
            $resolvedTool = $tool();
            if (! $resolvedTool instanceof Tool) {
                throw new InvalidArgumentException('Callable must return a Tool instance');
            }

            return $resolvedTool;
        } elseif ($tool instanceof Tool) {
            // Already instantiated
            return $tool;
        } else {
            throw new InvalidArgumentException('Tool must be a string, callable, or Tool instance');
        }
    }

    /**
     * Get all registered tools as resolved instances
     *
     * @return Tool[]
     */
    public function all(): array
    {
        $resolvedTools = [];

        foreach ($this->tools as $tool) {
            $resolvedTools[] = $this->resolveTool($tool);
        }

        return $resolvedTools;
    }

    /**
     * Get the count of registered tools
     */
    public function count(): int
    {
        return count($this->tools);
    }

    /**
     * Clear all registered tools
     */
    public function clear(): void
    {
        $this->tools = [];
    }
}
