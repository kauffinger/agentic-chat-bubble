# Agentic Chat Bubble

> An AI-powered chat bubble for Statamic that provides intelligent assistance with site content using Prism AI and Statamic search integration.

## Features

This addon provides:

- **AI-powered chat bubble** with streaming responses
- **Statamic search integration** via AI tools for content assistance
- **Markdown rendering** with syntax highlighting
- **Expandable thinking process** display
- **Mobile-responsive design** with floating chat window
- **Tool execution tracking** for transparency

## How to Install

### 1. Install via Composer

```bash
composer require kauffinger/agentic-chat-bubble
```

### 2. Frontend Integration

Add the chat bubble component to your layout template:

```antlers
<!-- In your layout file (e.g., resources/views/layout.antlers.html) -->
{{ livewire:agentic-chat-bubble }}
```

### 3. Import JavaScript Component

Import and register the `markdownProcessor` component in your main JavaScript file:

```js
// In resources/js/site.js
import markdownProcessor from '../../vendor/kauffinger/agentic-chat-bubble/resources/js/components/markdownProcessor.js';

// Register with Alpine.js
Alpine.data('markdownProcessor', markdownProcessor);
```

### 4. Import CSS Styles

The addon's styles need to be imported into your application's CSS:

#### For Statamic Peak users:

```css
/* In resources/css/peak.css */
@import '../../vendor/kauffinger/agentic-chat-bubble/resources/css/addon.css';
```

#### For other setups:

```css
/* In your main CSS file (e.g., resources/css/app.css or site.css) */
@import '../../vendor/kauffinger/agentic-chat-bubble/resources/css/addon.css';
```

**Important**: Make sure your Tailwind configuration scans the addon's views for classes:

```js
// In tailwind.config.js
module.exports = {
  content: [
    // ... your existing paths
    './vendor/kauffinger/agentic-chat-bubble/resources/views/**/*.blade.php',
  ],
  // ... rest of config
};
```

After adding the imports and updating Tailwind config, rebuild your assets:

```bash
npm run build
```

### 5. Publish Configuration (Optional)

To customize the addon settings, publish the config file:

```bash
php artisan vendor:publish --tag="agentic-chat-bubble-config"
```

This will create `config/agentic-chat-bubble.php` where you can configure:

- AI provider and model
- System prompt
- UI settings
- Max message length and other limits

### 6. Dependencies

Ensure your application has the following dependencies (likely already installed):

- Livewire
- Alpine.js with collapse plugin
- Prism AI package for chat functionality

## Configuration

After publishing the config file, you can customize:

### AI Settings

```php
'provider' => env('AGENTIC_CHAT_PROVIDER', 'openai'), // openai, anthropic, google, ollama
'model' => env('AGENTIC_CHAT_MODEL', 'gpt-4-mini'),
'system_prompt' => env('AGENTIC_CHAT_SYSTEM_PROMPT', '...'),
'max_steps' => env('AGENTIC_CHAT_MAX_STEPS', 5),
```

### UI Settings

```php
'ui' => [
    'position' => 'bottom-left', // bottom-left, bottom-right
    'title' => 'Assistant',
    'placeholder' => 'Type your message...',
    'thinking_button_text' => '🧠',
    'thinking_prose_size' => 'prose-sm',
],
```

You can also set these values using environment variables:

```env
AGENTIC_CHAT_PROVIDER=anthropic
AGENTIC_CHAT_MODEL=claude-3-sonnet
AGENTIC_CHAT_MAX_MESSAGE_LENGTH=2000
```

### Custom Tools

Register custom AI tools that extend the assistant's capabilities:

```php
'tools' => [
    // Default tools (Statamic search)
    \Kauffinger\AgenticChatBubble\Tools\GetAvailableStatamicIndexesTool::class,
    \Kauffinger\AgenticChatBubble\Tools\StatamicSearchTool::class,

    // Add your custom tools
    \App\Tools\WeatherTool::class,
    \App\Tools\DatabaseQueryTool::class,

    // Tools with dependencies via closure
    fn() => new \App\Tools\ApiTool(config('services.api_key')),
]
```

#### Creating Custom Tools

Custom tools must implement the `Prism\Prism\Tool` interface:

```php
namespace App\Tools;

use Prism\Prism\Tool;

class WeatherTool extends Tool
{
    public function __construct()
    {
        $this
            ->as('get_weather')
            ->for('Get current weather for a location')
            ->withStringParameter('location', 'City name or coordinates')
            ->using($this);
    }

    public function __invoke(string $location): string
    {
        // Your tool logic here
        return "The weather in {$location} is sunny and 72°F";
    }
}
```

#### Dynamic Tool Registration

You can also register tools programmatically in your AppServiceProvider or any service provider:

```php
use Kauffinger\AgenticChatBubble\ServiceProvider as ChatBubbleServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Register a single tool
        ChatBubbleServiceProvider::registerTool(\App\Tools\WeatherTool::class);

        // Register multiple tools
        ChatBubbleServiceProvider::registerTools([
            \App\Tools\DatabaseTool::class,
            fn() => new \App\Tools\ApiTool($this->app['api.client']),
        ]);

        // Conditionally register tools
        if (config('services.weather.enabled')) {
            ChatBubbleServiceProvider::registerTool(\App\Tools\WeatherTool::class);
        }
    }
}
```

This is useful for:

- Conditional tool registration based on environment or config
- Registering tools from other packages
- Tools that require complex initialization logic

## Usage

Once installed, users will see a floating chat bubble in the bottom-left corner that provides:

- Content search across your Statamic site
- AI-powered answers based on your site's content
- Streaming responses with real-time tool usage display
- Expandable "thinking" process for transparency

## Requirements

- Statamic 5.0+
- Prism AI package configured with OpenAI
- Alpine.js and Livewire for frontend functionality
