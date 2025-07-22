<?php

namespace Kauffinger\AgenticChatBubble;

use Kauffinger\AgenticChatBubble\Livewire\ChatBubbleComponent;
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
        $this->app->singleton('agentic-chat-bubble.tools', function ($app) {
            return collect();
        });
    }

    /**
     * Register a tool to be used by the chat bubble
     *
     * @param  string|callable|object  $tool
     */
    public static function registerTool($tool): void
    {
        app('agentic-chat-bubble.tools')->push($tool);
    }

    /**
     * Register multiple tools at once
     */
    public static function registerTools(array $tools): void
    {
        foreach ($tools as $tool) {
            static::registerTool($tool);
        }
    }
}
