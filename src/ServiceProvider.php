<?php

namespace Kauffinger\AgenticChatBubble;

use Kauffinger\AgenticChatBubble\Livewire\ChatBubbleComponent;
use Kauffinger\AgenticChatBubble\Services\ToolRegistry;
use Livewire\Livewire;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    /* @phpstan-ignore property.defaultValue */
    protected $vite = [
        'input' => [
            'resources/js/addon.js',
            'resources/css/addon.css',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    public function bootAddon()
    {
        // Register Livewire components
        Livewire::component('agentic-chat-bubble', ChatBubbleComponent::class);

        // Register views with custom namespace
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'agentic-chat-bubble');

        // Register services in the container
        $this->app->singleton(\Kauffinger\AgenticChatBubble\Services\StatamicSearchToolService::class);

        // Publish config file
        $this->publishes([
            __DIR__.'/../config/agentic-chat-bubble.php' => config_path('agentic-chat-bubble.php'),
        ], 'agentic-chat-bubble-config');

        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/agentic-chat-bubble.php', 'agentic-chat-bubble'
        );

        // Register singleton for tool management
        $this->app->singleton(ToolRegistry::class);
    }

    /**
     * Register a tool to be used by the chat bubble
     */
    public static function registerTool(string|callable|object $tool): void
    {
        app(ToolRegistry::class)->register($tool);
    }

    /**
     * Register multiple tools at once
     */
    public static function registerTools(array $tools): void
    {
        app(ToolRegistry::class)->registerMany($tools);
    }
}
