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
    public function register(string|callable|Tool $tool): void
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
            if (is_string($tool)) {
                // Class string - resolve from container
                $resolvedTools[] = app($tool);
            } elseif (is_callable($tool)) {
                // Closure or callable - invoke it
                $resolvedTool = $tool();
                if (! $resolvedTool instanceof Tool) {
                    throw new InvalidArgumentException('Callable must return a Tool instance');
                }
                $resolvedTools[] = $resolvedTool;
            } elseif ($tool instanceof Tool) {
                // Already instantiated
                $resolvedTools[] = $tool;
            } else {
                throw new InvalidArgumentException('Tool must be a string, callable, or Tool instance');
            }
        }

        return $resolvedTools;
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
            if (is_string($tool)) {
                // Class string - resolve from container
                $resolvedTools[] = app($tool);
            } elseif (is_callable($tool)) {
                // Closure or callable - invoke it
                $resolvedTool = $tool();
                if (! $resolvedTool instanceof Tool) {
                    throw new InvalidArgumentException('Callable must return a Tool instance');
                }
                $resolvedTools[] = $resolvedTool;
            } elseif ($tool instanceof Tool) {
                // Already instantiated
                $resolvedTools[] = $tool;
            } else {
                throw new InvalidArgumentException('Tool must be a string, callable, or Tool instance');
            }
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
