<?php

namespace Kauffinger\AgenticChatBubble\Livewire;

use Illuminate\View\View;
use Kauffinger\AgenticChatBubble\Actions\UpdateStreamDataFromPrismChunk;
use Kauffinger\AgenticChatBubble\Dtos\StreamData;
use Livewire\Attributes\Session;
use Livewire\Component;
use Prism\Prism\Enums\ChunkType;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class ChatBubbleComponent extends Component
{
    #[Session]
    public string $message = '';

    #[Session]
    public array $messages = [];

    public function mount(): void {}

    protected function rules(): array
    {
        return [
            'message' => 'required|string|max:'.config('agentic-chat-bubble.max_message_length', 1000),
        ];
    }

    public function sendMessage(): void
    {
        $this->validate();

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

        $provider = $this->getProvider();
        $model = config('agentic-chat-bubble.model', 'gpt-4-mini');
        $systemPrompt = config('agentic-chat-bubble.system_prompt');
        $maxSteps = config('agentic-chat-bubble.max_steps', 5);

        $generator = Prism::text()
            ->using($provider, $model)
            ->withSystemPrompt($systemPrompt)
            ->withMessages($this->messagesToPrism($this->messages))
            ->withTools($this->getTools())
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

    public function render(): View
    {
        /* @phpstan-ignore argument.type */
        return view('agentic-chat-bubble::livewire.chat-bubble-component');
    }

    protected function getProvider(): Provider
    {
        $providerString = config('agentic-chat-bubble.provider', 'openai');

        return match (strtolower($providerString)) {
            'openai' => Provider::OpenAI,
            'anthropic' => Provider::Anthropic,
            'google', 'gemini' => Provider::Gemini,
            'ollama' => Provider::Ollama,
            default => Provider::OpenAI,
        };
    }

    protected function getTools(): array
    {
        $tools = [];

        // Get tools from config
        $configuredTools = config('agentic-chat-bubble.tools', []);

        // Merge with dynamically registered tools
        $dynamicTools = app('agentic-chat-bubble.tools')->all();
        $allTools = array_merge($configuredTools, $dynamicTools);

        foreach ($allTools as $tool) {
            if (is_string($tool)) {
                // Class string - resolve from container
                $tools[] = app($tool);
            } elseif (is_callable($tool)) {
                // Closure or callable - invoke it
                $tools[] = $tool();
            } elseif (is_object($tool)) {
                // Already instantiated
                $tools[] = $tool;
            }
        }

        return $tools;
    }
}
