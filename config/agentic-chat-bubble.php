<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the AI provider and model to use for the chat bubble.
    | Supported providers: 'openai', 'anthropic', 'google', 'ollama'
    |
    */
    'provider' => env('AGENTIC_CHAT_PROVIDER', 'openai'),

    'model' => env('AGENTIC_CHAT_MODEL', 'gpt-4.1-mini'),

    /*
    |--------------------------------------------------------------------------
    | System Prompt
    |--------------------------------------------------------------------------
    |
    | The system prompt that guides the AI assistant's behavior.
    | You can customize this to match your site's needs.
    |
    */
    'system_prompt' => env('AGENTIC_CHAT_SYSTEM_PROMPT', 'You are a helpful assistant answering messages about this website. You have access to Statamic\'s search index via tools. Whatever is asked, search the index first and then answer the question. If you cannot find an answer, say so. Refuse to answer questions that are not related to this website or its content.'),

    /*
    |--------------------------------------------------------------------------
    | Chat Settings
    |--------------------------------------------------------------------------
    |
    | Various settings for the chat functionality.
    |
    */
    'max_steps' => env('AGENTIC_CHAT_MAX_STEPS', 5),

    'max_message_length' => env('AGENTIC_CHAT_MAX_MESSAGE_LENGTH', 1000),

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    |
    | Customize the appearance and behavior of the chat bubble.
    |
    */
    'ui' => [
        'position' => 'bottom-left',
        'title' => 'Assistant',
        'placeholder' => 'Type your message...',
        'thinking_button_text' => '🧠',
        'thinking_prose_size' => 'prose-sm',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for chat messages to prevent abuse.
    | Users can only send a certain number of messages per time period.
    |
    */
    'rate_limit' => [
        'enabled' => env('AGENTIC_CHAT_RATE_LIMIT_ENABLED', true),
        'max_messages' => env('AGENTIC_CHAT_RATE_LIMIT_MAX', 30),
        'decay_minutes' => env('AGENTIC_CHAT_RATE_LIMIT_DECAY', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | GDPR Compliance
    |--------------------------------------------------------------------------
    |
    | Configure GDPR consent requirements for the chat bubble.
    | When enabled, users must consent before messages are sent to AI providers.
    |
    */
    'gdpr' => [
        'enabled' => env('AGENTIC_CHAT_GDPR_ENABLED', false),
        'consent_text' => env(
            'AGENTIC_CHAT_GDPR_CONSENT_TEXT',
            'This chat uses AI services to process your messages. Your messages will be sent to our AI provider for processing. Do you consent to this data processing?'
        ),
        'consent_button_text' => env('AGENTIC_CHAT_GDPR_CONSENT_BUTTON', 'I Consent'),
        'decline_button_text' => env('AGENTIC_CHAT_GDPR_DECLINE_BUTTON', 'No Thanks'),
        'declined_message' => env(
            'AGENTIC_CHAT_GDPR_DECLINED_MESSAGE',
            'You need to provide consent to use the chat assistant. You can close this window and reopen it if you change your mind.'
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Tools
    |--------------------------------------------------------------------------
    |
    | Register custom tools that the AI assistant can use.
    | Tools must implement the Prism\Prism\Tool interface.
    |
    | You can register tools in several ways:
    | 1. As class strings (they will be instantiated via the container)
    | 2. As closures that return tool instances
    | 3. As already instantiated objects
    |
    */
    'tools' => [
        // Default tools
        \Kauffinger\AgenticChatBubble\Tools\GetAvailableStatamicIndexesTool::class,
        \Kauffinger\AgenticChatBubble\Tools\StatamicSearchTool::class,

        // Add your custom tools here
        // \App\Tools\MyCustomTool::class,
        // fn() => new \App\Tools\AnotherTool($dependency),
    ],
];
