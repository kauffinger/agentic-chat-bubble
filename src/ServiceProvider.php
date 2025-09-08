<?php

namespace Kauffinger\AgenticChatBubble;

use Kauffinger\AgenticChatBubble\Livewire\ChatBubbleComponent;
use Kauffinger\AgenticChatBubble\Services\StatamicSearchToolService;
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
        Livewire::component('agentic-chat-bubble', ChatBubbleComponent::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'agentic-chat-bubble');

        $this->app->singleton(StatamicSearchToolService::class);
        $this->app->singleton(ToolRegistry::class);

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'agentic-chat-bubble');

        $this->publishes([
            __DIR__.'/../config/agentic-chat-bubble.php' => config_path('agentic-chat-bubble.php'),
        ], 'agentic-chat-bubble-config');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/agentic-chat-bubble'),
        ], 'agentic-chat-bubble-translations');

        $this->mergeConfigFrom(
            __DIR__.'/../config/agentic-chat-bubble.php', 'agentic-chat-bubble'
        );
    }

    /**
     * Register a tool to be used by the chat bubble
     */
    public static function registerTool(mixed $tool): void
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
