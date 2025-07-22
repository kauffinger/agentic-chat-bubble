@props([
    'message' => null,
    'isFirst' => false,
    'isLoading' => false,
    'wireStream' => null,
    'wireReplace' => false,
    'showHeading' => true,
    'thinkingButtonText' => config('agentic-chat-bubble.ui.thinking_button_text', '🧠'),
    'thinkingProseSize' => config('agentic-chat-bubble.ui.thinking_prose_size', 'prose-sm'),
])

<div class="@if ($isFirst) flex-1 @endif flex w-full flex-row justify-start">
    <div
        class="max-h-fit max-w-[70%] border-2 border-neutral-800 bg-white p-3 break-words text-neutral-900"
        x-data="markdownProcessor()"
    >
        @if ($isLoading)
            <!-- Thinking indicator -->
            <div x-show="isCurrentlyThinking()" class="flex items-center gap-2">
                <div class="flex space-x-1">
                    <div class="h-2 w-2 animate-pulse bg-neutral-800"></div>
                    <div class="h-2 w-2 animate-pulse bg-neutral-800 [animation-delay:0.2s]"></div>
                    <div class="h-2 w-2 animate-pulse bg-neutral-800 [animation-delay:0.4s]"></div>
                </div>
                <span class="font-mono text-xs uppercase">PROCESSING...</span>
            </div>

            <!-- Tool usage indicator -->
            <div x-show="isCurrentlyUsingTools()" class="flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                    />
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                    />
                </svg>
                <span class="font-mono text-xs uppercase">EXECUTING TOOLS...</span>
            </div>
        @endif

        <!-- Thinking preview (collapsible) -->
        <div x-show="hasThinking()" class="border-t-2 border-neutral-800 pt-3">
            <button
                @click="toggleThinking()"
                class="flex items-center gap-1 px-2 py-1 font-mono text-xs uppercase transition-colors hover:bg-neutral-800 hover:text-white"
            >
                <span x-show="!showThinking">▶</span>
                <span x-show="showThinking">▼</span>
                {{ $thinkingButtonText }}
            </button>
            <div x-show="showThinking" x-collapse class="mt-2">
                <div class="border-2 border-neutral-800 bg-gray-100 p-3 text-neutral-900">
                    <article
                        wire:ignore
                        class="prose prose-zinc {{ $thinkingProseSize }} prose-p:m-0 prose-code:font-mono prose-pre:text-xs max-w-none break-words"
                        x-html="thinkingHtml"
                    ></article>
                </div>
            </div>
        </div>

        <!-- Tool calls display -->
        <div x-show="hasToolCalls()" class="border-t-2 border-neutral-800 pt-3">
            <template x-for="toolCall in streamData.toolCalls" :key="toolCall.resultId">
                <div class="mt-1 inline-flex items-center gap-2 text-xs">
                    <span class="h-2 w-2 bg-neutral-800"></span>
                    <span x-text="toolCall.name" class="align-baseline font-mono uppercase"></span>
                </div>
            </template>
        </div>

        <!-- Main content -->
        <div class="text-neutral-900">
            <span
                x-ref="raw"
                class="hidden"
                @if ($wireStream)
                    wire:stream="{{ $wireStream }}"
                @endif
                @if ($wireReplace)
                    wire:replace
                @endif
            >
                {{ $isLoading ? '' : json_encode($message['parts']) }}
            </span>
            <article
                wire:ignore
                class="prose prose-sm prose-p:my-2 prose-headings:font-mono prose-headings:uppercase prose-headings:tracking-wider prose-code:font-mono prose-code:bg-gray-100 prose-code:text-neutral-900 prose-code:px-1 prose-pre:border-2 prose-pre:border-neutral-800 prose-pre:bg-gray-50 prose-pre:p-4 prose-pre:mb-2 prose-strong:font-bold prose-em:not-italic prose-em:bg-gray-100 prose-em:text-neutral-900 prose-em:px-1 max-w-none min-w-0 overflow-hidden break-words [&_pre]:shadow-[4px_4px_0px_0px_rgba(38,38,38,1)]"
                x-html="html"
                @if ($isLoading)
                    x-show="html.length > 0"
                @endif
            ></article>
            @if (! $isLoading && $message && isset($message['timestamp']))
                <p class="mt-1 text-xs text-gray-500">{{ $message['timestamp'] }}</p>
            @endif
        </div>
    </div>
</div>
