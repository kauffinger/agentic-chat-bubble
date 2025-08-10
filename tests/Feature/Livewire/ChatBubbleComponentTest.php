<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Kauffinger\AgenticChatBubble\Actions\UpdateStreamDataFromPrismChunk;
use Kauffinger\AgenticChatBubble\Dtos\StreamData;
use Kauffinger\AgenticChatBubble\Livewire\ChatBubbleComponent;
use Prism\Prism\Enums\ChunkType;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\Text\Chunk;
use Prism\Prism\Tool;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\ToolCall;
use Prism\Prism\ValueObjects\ToolResult;
use Prism\Prism\ValueObjects\Usage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Config::set('agentic-chat-bubble.provider', 'openai');
    Config::set('agentic-chat-bubble.model', 'gpt-4-mini');
    Config::set('agentic-chat-bubble.system_prompt', 'You are a helpful assistant.');
    Config::set('agentic-chat-bubble.max_steps', 5);
    Config::set('agentic-chat-bubble.max_message_length', 1000);
    Config::set('agentic-chat-bubble.rate_limit.enabled', true);
    Config::set('agentic-chat-bubble.rate_limit.max_messages', 30);
    Config::set('agentic-chat-bubble.rate_limit.decay_minutes', 1);
    Config::set('agentic-chat-bubble.tools', []);

    // Initialize the dynamic tools container
    app()->singleton('agentic-chat-bubble.tools', function () {
        return new class
        {
            private array $tools = [];

            public function register(string $key, $tool): void
            {
                $this->tools[$key] = $tool;
            }

            public function all(): array
            {
                return $this->tools;
            }
        };
    });
});

// No need for Mockery::close() when using Pest Laravel's mock() helper

describe('Component Rendering & Initialization', function () {
    it('can render the chat bubble component', function () {
        livewire(ChatBubbleComponent::class)
            ->assertOk()
            ->assertViewIs('agentic-chat-bubble::livewire.chat-bubble-component');
    });

    it('initializes with empty messages and message input', function () {
        livewire(ChatBubbleComponent::class)
            ->assertSet('messages', [])
            ->assertSet('message', '');
    });

    it('shows start conversation text when no messages', function () {
        livewire(ChatBubbleComponent::class)
            ->assertSee('Start a conversation...');
    });
});

describe('Message Sending & Validation', function () {
    it('validates required message', function () {
        livewire(ChatBubbleComponent::class)
            ->call('sendMessage')
            ->assertHasErrors(['message' => 'required']);
    });

    it('validates max message length', function () {
        Config::set('agentic-chat-bubble.max_message_length', 10);

        livewire(ChatBubbleComponent::class)
            ->set('message', 'This is a very long message that exceeds the limit')
            ->call('sendMessage')
            ->assertHasErrors(['message' => 'max']);
    });

    it('sends valid message and clears input', function () {
        // Mock RateLimiter to allow the message
        RateLimiter::shouldReceive('tooManyAttempts')
            ->andReturn(false);

        $component = livewire(ChatBubbleComponent::class)
            ->set('message', 'Hello AI')
            ->call('sendMessage')
            ->assertSet('message', '') // Input cleared
            ->assertHasNoErrors();

        expect($component->messages)->toHaveCount(1);
        expect($component->messages[0]['role'])->toBe('user');
        expect($component->messages[0]['parts']['text'])->toBe('Hello AI');
        expect($component->messages[0])->toHaveKey('id');
        expect($component->messages[0])->toHaveKey('timestamp');
    });

    it('triggers JavaScript chat loop after sending message', function () {
        RateLimiter::shouldReceive('tooManyAttempts')
            ->andReturn(false);

        // The JavaScript dispatch happens via $this->js() which is hard to test directly
        // We'll verify the message is added and cleared which indicates sendMessage worked
        $component = livewire(ChatBubbleComponent::class)
            ->set('message', 'Test message')
            ->call('sendMessage');

        expect($component->messages)->toHaveCount(1);
        expect($component->message)->toBe('');
    });
});

describe('Rate Limiting', function () {
    it('enforces rate limiting when enabled', function () {
        Config::set('agentic-chat-bubble.rate_limit.enabled', true);

        RateLimiter::shouldReceive('tooManyAttempts')
            ->andReturn(true);
        RateLimiter::shouldReceive('availableIn')
            ->andReturn(30);

        livewire(ChatBubbleComponent::class)
            ->set('message', 'Test')
            ->call('sendMessage')
            ->assertHasErrors(['message']);

        // Verify the error message contains rate limit text
        $component = livewire(ChatBubbleComponent::class);
        $component->set('message', 'Test');
        $component->call('sendMessage');

        $errors = $component->errors()->get('message');
        expect($errors[0])->toContain('Rate limit exceeded');
    });

    it('allows messages when rate limit disabled', function () {
        Config::set('agentic-chat-bubble.rate_limit.enabled', false);

        livewire(ChatBubbleComponent::class)
            ->set('message', 'Test')
            ->call('sendMessage')
            ->assertHasNoErrors()
            ->assertSet('message', '');
    });

    it('adds rate limit error message to chat when tool loop is rate limited', function () {
        Config::set('agentic-chat-bubble.rate_limit.enabled', true);

        RateLimiter::shouldReceive('tooManyAttempts')
            ->andReturn(true);
        RateLimiter::shouldReceive('availableIn')
            ->once()
            ->andReturn(45);

        $component = livewire(ChatBubbleComponent::class);
        $component->set('messages', [
            ['role' => 'user', 'parts' => ['text' => 'Hi'], 'timestamp' => now()->format('g:i A'), 'id' => '1'],
        ]);
        $component->call('runChatToolLoop');

        expect($component->messages)->toHaveCount(2);
        expect($component->messages[1]['role'])->toBe('assistant');
        expect($component->messages[1]['parts']['text'])->toContain('Rate limit exceeded');
        expect($component->messages[1]['parts']['text'])->toContain('45 seconds');
    });

    it('records rate limit hit when processing messages', function () {
        Config::set('agentic-chat-bubble.rate_limit.enabled', true);
        Config::set('agentic-chat-bubble.rate_limit.decay_minutes', 2);

        RateLimiter::shouldReceive('tooManyAttempts')
            ->once()
            ->andReturn(false);
        RateLimiter::shouldReceive('hit')
            ->once()
            ->withArgs(function ($key, $seconds) {
                return str_starts_with($key, 'agentic-chat-bubble:') && $seconds === 120;
            });

        // Mock Prism to return a simple response
        $fakeResponse = TextResponseFake::make()
            ->withText('Response')
            ->withUsage(new Usage(10, 20));
        Prism::fake([$fakeResponse]);

        $component = livewire(ChatBubbleComponent::class);
        $component->set('messages', [
            ['role' => 'user', 'parts' => ['text' => 'Test'], 'timestamp' => now()->format('g:i A'), 'id' => '1'],
        ]);
        $component->call('runChatToolLoop');
    });
});

describe('AI Integration with Prism', function () {
    it('processes AI response with text only', function () {
        RateLimiter::shouldReceive('tooManyAttempts')->andReturn(false);
        RateLimiter::shouldReceive('hit')->once();

        $fakeResponse = TextResponseFake::make()
            ->withText('Hello! How can I help you?')
            ->withUsage(new Usage(10, 20))
            ->withFinishReason(FinishReason::Stop);

        Prism::fake([$fakeResponse]);

        $component = livewire(ChatBubbleComponent::class);
        $component->set('messages', [
            ['role' => 'user', 'parts' => ['text' => 'Hi'], 'timestamp' => now()->format('g:i A'), 'id' => '1'],
        ]);
        $component->call('runChatToolLoop');

        expect($component->messages)->toHaveCount(2);
        expect($component->messages[1]['role'])->toBe('assistant');
        expect($component->messages[1]['parts']['text'])->toBe('Hello! How can I help you?');
    });

    it('handles tool calls and results', function () {
        RateLimiter::shouldReceive('tooManyAttempts')->andReturn(false);
        RateLimiter::shouldReceive('hit')->once();

        // Create a response with tool calls
        $fakeResponse = TextResponseFake::make()
            ->withToolCalls([
                new ToolCall(
                    id: 'tool_123',
                    name: 'search',
                    arguments: ['query' => 'documentation']
                ),
            ])
            ->withText('Based on the search results, I found 5 documents.')
            ->withUsage(new Usage(20, 30))
            ->withFinishReason(FinishReason::Stop);

        Prism::fake([$fakeResponse]);

        $component = livewire(ChatBubbleComponent::class);
        $component->set('messages', [
            ['role' => 'user', 'parts' => ['text' => 'Search for docs'], 'timestamp' => now()->format('g:i A'), 'id' => '1'],
        ]);
        $component->call('runChatToolLoop');

        // The component processes tool calls and adds the assistant message
        expect($component->messages)->toHaveCount(2);
        expect($component->messages[1]['role'])->toBe('assistant');
        expect($component->messages[1]['parts'])->toHaveKey('toolCalls');
        expect($component->messages[1]['parts']['text'])->toContain('5 documents');
    });

    it('streams responses correctly', function () {
        RateLimiter::shouldReceive('tooManyAttempts')->andReturn(false);
        RateLimiter::shouldReceive('hit')->once();

        $fakeResponse = TextResponseFake::make()
            ->withText('This is a streaming response')
            ->withUsage(new Usage(10, 20))
            ->withFinishReason(FinishReason::Stop);

        Prism::fake([$fakeResponse]);

        $component = livewire(ChatBubbleComponent::class);
        $component->set('messages', [
            ['role' => 'user', 'parts' => ['text' => 'Test'], 'timestamp' => now()->format('g:i A'), 'id' => '1'],
        ]);
        $component->call('runChatToolLoop');

        // Verify the response was processed (streaming is handled internally)
        expect($component->messages)->toHaveCount(2);
        expect($component->messages[1]['parts']['text'])->toBe('This is a streaming response');
    });

    it('handles empty messages array gracefully', function () {
        $component = livewire(ChatBubbleComponent::class);
        $component->set('messages', []);
        $component->call('runChatToolLoop');

        // Should not add any messages when array is empty
        expect($component->messages)->toHaveCount(0);
    });
});

describe('Provider Configuration', function () {
    it('maps openai provider correctly', function () {
        Config::set('agentic-chat-bubble.provider', 'openai');

        $component = livewire(ChatBubbleComponent::class)->instance();
        $reflection = new ReflectionMethod($component, 'getProvider');
        $reflection->setAccessible(true);

        expect($reflection->invoke($component))->toBe(Provider::OpenAI);
    });

    it('maps anthropic provider correctly', function () {
        Config::set('agentic-chat-bubble.provider', 'anthropic');

        $component = livewire(ChatBubbleComponent::class)->instance();
        $reflection = new ReflectionMethod($component, 'getProvider');
        $reflection->setAccessible(true);

        expect($reflection->invoke($component))->toBe(Provider::Anthropic);
    });

    it('maps google and gemini providers correctly', function () {
        Config::set('agentic-chat-bubble.provider', 'google');

        $component = livewire(ChatBubbleComponent::class)->instance();
        $reflection = new ReflectionMethod($component, 'getProvider');
        $reflection->setAccessible(true);

        expect($reflection->invoke($component))->toBe(Provider::Gemini);

        Config::set('agentic-chat-bubble.provider', 'gemini');

        $component = livewire(ChatBubbleComponent::class)->instance();
        $reflection = new ReflectionMethod($component, 'getProvider');
        $reflection->setAccessible(true);

        expect($reflection->invoke($component))->toBe(Provider::Gemini);
    });

    it('maps ollama provider correctly', function () {
        Config::set('agentic-chat-bubble.provider', 'ollama');

        $component = livewire(ChatBubbleComponent::class)->instance();
        $reflection = new ReflectionMethod($component, 'getProvider');
        $reflection->setAccessible(true);

        expect($reflection->invoke($component))->toBe(Provider::Ollama);
    });

    it('defaults to openai for unknown provider', function () {
        Config::set('agentic-chat-bubble.provider', 'unknown-provider');

        $component = livewire(ChatBubbleComponent::class)->instance();
        $reflection = new ReflectionMethod($component, 'getProvider');
        $reflection->setAccessible(true);

        expect($reflection->invoke($component))->toBe(Provider::OpenAI);
    });
});

describe('Tool Loading', function () {
    it('loads tools from config as class strings', function () {
        // Create a simple mock tool using the existing tools from the package
        $mockTool = (new Tool())
            ->as('test_tool')
            ->for('Test tool')
            ->using(fn () => 'test result');

        app()->bind('TestToolClass', fn () => $mockTool);
        Config::set('agentic-chat-bubble.tools', ['TestToolClass']);

        $component = livewire(ChatBubbleComponent::class)->instance();
        $reflection = new ReflectionMethod($component, 'getTools');
        $reflection->setAccessible(true);

        $tools = $reflection->invoke($component);
        expect($tools)->toHaveCount(1);
        expect($tools[0])->toBe($mockTool);
    });

    it('loads tools from config as callables', function () {
        $mockTool = (new Tool())
            ->as('callable_tool')
            ->for('Callable tool')
            ->using(fn () => 'callable result');

        Config::set('agentic-chat-bubble.tools', [
            fn () => $mockTool,
        ]);

        $component = livewire(ChatBubbleComponent::class)->instance();
        $reflection = new ReflectionMethod($component, 'getTools');
        $reflection->setAccessible(true);

        $tools = $reflection->invoke($component);
        expect($tools)->toHaveCount(1);
        expect($tools[0])->toBe($mockTool);
    });

    it('loads tools from config as objects', function () {
        $mockTool = (new Tool())
            ->as('object_tool')
            ->for('Object tool')
            ->using(fn () => 'object result');

        Config::set('agentic-chat-bubble.tools', [$mockTool]);

        $component = livewire(ChatBubbleComponent::class)->instance();
        $reflection = new ReflectionMethod($component, 'getTools');
        $reflection->setAccessible(true);

        $tools = $reflection->invoke($component);
        expect($tools)->toHaveCount(1);
        expect($tools[0])->toBe($mockTool);
    });

    it('loads dynamically registered tools', function () {
        $mockTool1 = (new Tool())
            ->as('dynamic1')
            ->for('Dynamic tool 1')
            ->using(fn () => 'result1');

        $mockTool2 = (new Tool())
            ->as('dynamic2')
            ->for('Dynamic tool 2')
            ->using(fn () => 'result2');

        app('agentic-chat-bubble.tools')->register('tool1', $mockTool1);
        app('agentic-chat-bubble.tools')->register('tool2', $mockTool2);

        $component = livewire(ChatBubbleComponent::class)->instance();
        $reflection = new ReflectionMethod($component, 'getTools');
        $reflection->setAccessible(true);

        $tools = $reflection->invoke($component);
        expect($tools)->toHaveCount(2);
    });

    it('merges config and dynamic tools', function () {
        $configTool = (new Tool())
            ->as('config_tool')
            ->for('Config tool')
            ->using(fn () => 'config result');

        $dynamicTool = (new Tool())
            ->as('dynamic_tool')
            ->for('Dynamic tool')
            ->using(fn () => 'dynamic result');

        Config::set('agentic-chat-bubble.tools', [$configTool]);
        app('agentic-chat-bubble.tools')->register('dynamic', $dynamicTool);

        $component = livewire(ChatBubbleComponent::class)->instance();
        $reflection = new ReflectionMethod($component, 'getTools');
        $reflection->setAccessible(true);

        $tools = $reflection->invoke($component);
        expect($tools)->toHaveCount(2);
        expect($tools[0])->toBe($configTool);
        expect($tools[1])->toBe($dynamicTool);
    });
});

describe('Message Conversion', function () {
    it('converts user messages to Prism format', function () {
        $messages = [
            ['role' => 'user', 'parts' => ['text' => 'Hello'], 'timestamp' => now()->format('g:i A'), 'id' => '1'],
        ];

        $component = livewire(ChatBubbleComponent::class)->instance();
        $reflection = new ReflectionMethod($component, 'messagesToPrism');
        $reflection->setAccessible(true);

        $prismMessages = $reflection->invoke($component, $messages);

        expect($prismMessages)->toHaveCount(1);
        expect($prismMessages[0])->toBeInstanceOf(UserMessage::class);
        expect($prismMessages[0]->content)->toBe('Hello');
    });

    it('converts assistant messages to Prism format', function () {
        $messages = [
            ['role' => 'assistant', 'parts' => ['text' => 'Hi there', 'tool_calls' => []], 'timestamp' => now()->format('g:i A')],
        ];

        $component = livewire(ChatBubbleComponent::class)->instance();
        $reflection = new ReflectionMethod($component, 'messagesToPrism');
        $reflection->setAccessible(true);

        $prismMessages = $reflection->invoke($component, $messages);

        expect($prismMessages)->toHaveCount(1);
        expect($prismMessages[0])->toBeInstanceOf(AssistantMessage::class);
        expect($prismMessages[0]->content)->toBe('Hi there');
    });

    it('converts tool result messages to Prism format', function () {
        $toolResults = [
            ['result' => 'Search results', 'toolName' => 'search'],
        ];

        $messages = [
            ['role' => 'tool_result', 'parts' => ['tool_results' => $toolResults], 'timestamp' => now()->format('g:i A')],
        ];

        $component = livewire(ChatBubbleComponent::class)->instance();
        $reflection = new ReflectionMethod($component, 'messagesToPrism');
        $reflection->setAccessible(true);

        $prismMessages = $reflection->invoke($component, $messages);

        expect($prismMessages)->toHaveCount(1);
        expect($prismMessages[0])->toBeInstanceOf(ToolResultMessage::class);
    });

    it('throws exception for unknown message role', function () {
        $messages = [
            ['role' => 'unknown', 'parts' => ['text' => 'Test'], 'timestamp' => now()->format('g:i A')],
        ];

        $component = livewire(ChatBubbleComponent::class)->instance();
        $reflection = new ReflectionMethod($component, 'messagesToPrism');
        $reflection->setAccessible(true);

        expect(fn () => $reflection->invoke($component, $messages))
            ->toThrow(\InvalidArgumentException::class, 'Unknown message role: unknown');
    });
});

describe('Reset Chat Functionality', function () {
    it('resets chat when requested', function () {
        $component = livewire(ChatBubbleComponent::class)
            ->set('messages', [
                ['role' => 'user', 'parts' => ['text' => 'Test'], 'timestamp' => now()->format('g:i A'), 'id' => '1'],
                ['role' => 'assistant', 'parts' => ['text' => 'Response'], 'timestamp' => now()->format('g:i A')],
            ])
            ->set('message', 'Current input')
            ->call('resetChat')
            ->assertSet('messages', [])
            ->assertSet('message', '');
    });
});

describe('Session Persistence', function () {
    it('persists messages in session', function () {
        $messages = [
            ['role' => 'user', 'parts' => ['text' => 'Hello'], 'timestamp' => now()->format('g:i A'), 'id' => '1'],
        ];

        // Set messages on first component
        $component1 = livewire(ChatBubbleComponent::class)
            ->set('messages', $messages);

        // Create new component instance - should restore from session
        $component2 = livewire(ChatBubbleComponent::class);

        expect($component2->messages)->toBe($messages);
    });

    it('persists message input in session', function () {
        // Set message on first component
        $component1 = livewire(ChatBubbleComponent::class)
            ->set('message', 'Test message');

        // Create new component instance - should restore from session
        $component2 = livewire(ChatBubbleComponent::class);

        expect($component2->message)->toBe('Test message');
    });
});

describe('Rate Limit Key Generation', function () {
    it('generates rate limit key with session id', function () {
        $component = livewire(ChatBubbleComponent::class)->instance();
        $reflection = new ReflectionMethod($component, 'getRateLimitKey');
        $reflection->setAccessible(true);

        $key = $reflection->invoke($component);

        expect($key)->toStartWith('agentic-chat-bubble:');
        expect($key)->toContain(session()->getId());
    });
});

describe('UpdateStreamDataFromPrismChunk Action', function () {
    it('updates stream data with text chunks', function () {
        $streamData = new StreamData;
        $action = new UpdateStreamDataFromPrismChunk;

        $chunk = new Chunk(
            chunkType: ChunkType::Text,
            text: 'Hello world',
            toolCalls: [],
            toolResults: [],
            meta: null
        );

        $action->handle($streamData, $chunk);

        expect($streamData->text)->toBe('Hello world');
    });

    it('updates stream data with tool calls', function () {
        $streamData = new StreamData;
        $action = new UpdateStreamDataFromPrismChunk;

        $toolCall = new ToolCall(
            id: 'tool_123',
            name: 'search',
            arguments: ['query' => 'test']
        );

        $chunk = new Chunk(
            chunkType: ChunkType::ToolCall,
            text: '',
            toolCalls: [$toolCall],
            toolResults: [],
            meta: null
        );

        $action->handle($streamData, $chunk);

        expect($streamData->toolCalls)->toHaveCount(1);
        expect($streamData->toolCalls[0]['name'])->toBe('search');
        expect($streamData->toolCalls[0]['id'])->toBe('tool_123');
    });

    it('updates stream data with tool results', function () {
        $streamData = new StreamData;
        $action = new UpdateStreamDataFromPrismChunk;

        $toolResult = new ToolResult(
            toolCallId: 'tool_123',
            toolName: 'search',
            args: ['query' => 'test'],
            result: 'Search completed'
        );

        $chunk = new Chunk(
            chunkType: ChunkType::ToolResult,
            text: '',
            toolCalls: [],
            toolResults: [$toolResult],
            meta: null
        );

        $action->handle($streamData, $chunk);

        expect($streamData->toolResults)->toHaveCount(1);
        expect($streamData->toolResults[0]['result'])->toBe('Search completed');
        expect($streamData->toolResults[0]['toolName'])->toBe('search');
    });
});
