<?php

namespace Kauffinger\AgenticChatBubble\Livewire;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Kauffinger\AgenticChatBubble\Actions\UpdateStreamDataFromPrismChunk;
use Kauffinger\AgenticChatBubble\Dtos\StreamData;
use Kauffinger\AgenticChatBubble\Services\ToolRegistry;
use Livewire\Attributes\Session;
use Livewire\Component;
use Prism\Prism\Enums\ChunkType;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Statamic\Facades\Config;

class ChatBubbleComponent extends Component
{
    #[Session]
    public string $message = '';

    #[Session]
    public array $messages = [];

    #[Session]
    public bool $hasGdprConsent = false;

    #[Session]
    public bool $hasDeclinedGdpr = false;

    public function mount(): void {}

    protected function rules(): array
    {
        return [
            'message' => 'required|string|max:'.Config::get('agentic-chat-bubble.max_message_length', 1000),
        ];
    }

    public function sendMessage(): void
    {
        $this->validate();

        // Check GDPR consent if enabled
        if (Config::get('agentic-chat-bubble.gdpr.enabled', false) && ! $this->hasGdprConsent) {
            // Client-side will handle showing the modal
            return;
        }

        // Check rate limiting if enabled
        if (Config::get('agentic-chat-bubble.rate_limit.enabled', true)) {
            $key = $this->getRateLimitKey();
            $maxAttempts = Config::get('agentic-chat-bubble.rate_limit.max_messages', 30);

            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $seconds = RateLimiter::availableIn($key);
                $this->addError('message', "Rate limit exceeded. Please wait {$seconds} seconds before sending another message.");

                return;
            }
        }

        $this->messages[] = [
            'id' => uniqid(),
            'parts' => ['text' => $this->message],
            'role' => 'user',
            'timestamp' => now()->format('g:i A'),
        ];

        $this->message = '';
        $this->js('$wire.runChatToolLoop()');
    }

    public function runChatToolLoop(UpdateStreamDataFromPrismChunk $updateStreamDataFromPrismChunk): void
    {
        if (empty($this->messages)) {
            return;
        }

        // Check GDPR consent if enabled
        if (Config::get('agentic-chat-bubble.gdpr.enabled', false) && ! $this->hasGdprConsent) {
            return;
        }

        // Check rate limiting if enabled
        if (Config::get('agentic-chat-bubble.rate_limit.enabled', true)) {
            $key = $this->getRateLimitKey();
            $maxAttempts = Config::get('agentic-chat-bubble.rate_limit.max_messages', 30);
            $decayMinutes = Config::get('agentic-chat-bubble.rate_limit.decay_minutes', 1);

            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $seconds = RateLimiter::availableIn($key);

                // Fake an assistant error message
                $this->messages[] = [
                    'id' => uniqid(),
                    'parts' => ['text' => "⚠️ Rate limit exceeded. Please wait {$seconds} seconds before sending another message."],
                    'role' => 'assistant',
                    'timestamp' => now()->format('g:i A'),
                ];

                return;
            }

            // Record the attempt
            RateLimiter::hit($key, $decayMinutes * 60);
        }

        $provider = $this->getProvider();
        $model = Config::get('agentic-chat-bubble.model', 'gpt-4-mini');
        $systemPrompt = Config::get('agentic-chat-bubble.system_prompt');
        $maxSteps = Config::get('agentic-chat-bubble.max_steps', 5);

        $tools = app(ToolRegistry::class)->getAllTools();

        $generator = Prism::text()
            ->using($provider, $model)
            ->withSystemPrompt($systemPrompt)
            ->withMessages($this->messagesToPrism($this->messages))
            ->withTools($tools)
            ->withMaxSteps($maxSteps)
            ->asStream();

        $streamData = new StreamData;
        foreach ($generator as $chunk) {
            $updateStreamDataFromPrismChunk->handle($streamData, $chunk);

            if ($chunk->chunkType === ChunkType::Meta) {
                continue;
            }

            $this->stream(
                'streamed-message',
                json_encode([...$streamData->toArray(), 'currentChunkType' => $chunk->chunkType->value]),
                true
            );
        }

        if ($streamData->toolResults !== []) {
            $this->messages[] = [
                'id' => uniqid(),
                'parts' => ['toolResults' => $streamData->toolResults],
                'role' => 'tool_result',
                'timestamp' => now()->format('g:i A'),
            ];
        }

        $this->messages[] = [
            'parts' => $streamData->toArray(),
            'role' => 'assistant',
            'timestamp' => now()->format('g:i A'),
        ];
    }

    private function messagesToPrism(array $messages): array
    {
        return collect($messages)->map(function ($message) {
            return match ($message['role']) {
                'user' => new UserMessage($message['parts']['text'] ?? ''),
                'assistant' => new AssistantMessage($message['parts']['text'] ?? '', $message['parts']['tool_calls'] ?? []),
                'tool_result' => new ToolResultMessage($message['parts']['tool_results'] ?? []),
                default => throw new \InvalidArgumentException('Unknown message role: '.$message['role']),
            };
        })->all();
    }

    public function resetChat(): void
    {
        $this->messages = [];
        $this->message = '';
    }

    public function giveGdprConsent(): void
    {
        $this->hasGdprConsent = true;
        $this->hasDeclinedGdpr = false;
    }

    public function declineGdprConsent(): void
    {
        $this->hasGdprConsent = false;
        $this->hasDeclinedGdpr = true;
    }

    public function render(): View
    {
        /* @phpstan-ignore argument.type */
        return view('agentic-chat-bubble::livewire.chat-bubble-component');
    }

    protected function getProvider(): Provider
    {
        $providerString = Config::get('agentic-chat-bubble.provider', 'openai');

        return match (strtolower($providerString)) {
            'openai' => Provider::OpenAI,
            'anthropic' => Provider::Anthropic,
            'google', 'gemini' => Provider::Gemini,
            'ollama' => Provider::Ollama,
            default => Provider::OpenAI,
        };
    }

    protected function getRateLimitKey(): string
    {
        // Use session ID as the unique identifier for rate limiting
        return 'agentic-chat-bubble:'.session()->getId();
    }
}
