<div>
    {{-- Chat Bubble Button --}}
    <div
        x-data="{
            open: false,
            isMobile: window.innerWidth <= 640,
        }"
        @resize.window="isMobile = window.innerWidth <= 640"
        class="fixed bottom-6 left-6 z-50"
    >
        {{-- Floating Chat Window --}}
        <div
            x-show="open"
            x-trap.noscroll="open && isMobile"
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="scale-95 opacity-0"
            x-transition:enter-end="scale-100 opacity-100"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-start="scale-100 opacity-100"
            x-transition:leave-end="scale-95 opacity-0"
            @click.outside="!isMobile && (open = false)"
            @keydown.escape="open = false"
            class="fixed inset-0 h-full w-full border-2 border-neutral-800 bg-white sm:absolute sm:inset-auto sm:bottom-20 sm:left-0 sm:h-[500px] sm:w-[380px] sm:border-2 sm:shadow-lg"
            style="display: none"
        >
            {{-- Chat Header --}}
            <div class="flex items-center justify-between border-b-2 border-neutral-800 p-4">
                <h3 class="text-lg font-bold">{{ config('agentic-chat-bubble.ui.title', 'Assistant') }}</h3>
                <div class="flex items-center gap-2">
                    <button
                        wire:click="resetChat"
                        wire:confirm="Are you sure you want to clear the chat history?"
                        class="flex h-8 w-8 items-center justify-center transition-colors hover:bg-neutral-800 hover:text-white"
                        aria-label="Reset chat"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                            />
                        </svg>
                    </button>
                    <button
                        @click="open = false"
                        class="flex h-8 w-8 items-center justify-center transition-colors hover:bg-neutral-800 hover:text-white"
                        aria-label="Close chat"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path
                                fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Chat Content Area --}}
            <div class="py-4 pl-4" x-ref="chatContainer">
                @if (empty($messages))
                    <p class="mt-8 text-center text-gray-500">Start a conversation...</p>
                @else
                    <div class="space-y-4">
                        <div
                            class="mx-auto flex h-[350px] max-w-3xl flex-1 flex-col-reverse gap-4 overflow-y-scroll pt-1 pb-8"
                            x-data
                            x-init="$el.scrollTop = $el.scrollHeight"
                        >
                            <div
                                wire:loading.flex
                                wire:target="runChatToolLoop"
                                wire:key="loading-{{ count($messages) }}"
                                class="relative mx-auto hidden w-full max-w-4xl flex-1"
                            >
                                @include('agentic-chat-bubble::components.assistant-message', ['isLoading' => true, 'wireStream' => 'streamed-message', 'wireReplace' => true])
                            </div>

                            @foreach (array_reverse($messages) as $msg)
                                @if ($msg['role'] === 'user')
                                    <div class="flex justify-end">
                                        <div
                                            class="max-w-[70%] border-2 border-neutral-800 bg-neutral-900 p-3 break-words text-white"
                                        >
                                            <p class="text-sm break-words">{{ $msg['parts']['text'] }}</p>
                                            <p class="mt-1 text-xs text-gray-300">{{ $msg['timestamp'] }}</p>
                                        </div>
                                    </div>
                                @elseif ($msg['role'] === 'assistant')
                                    @include('agentic-chat-bubble::components.assistant-message', ['message' => $msg, 'isFirst' => $loop->first])
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Chat Input Area --}}
            <div class="absolute right-0 bottom-0 left-0 border-t-2 border-neutral-800 bg-white p-4">
                <form wire:submit="sendMessage" class="flex flex-col gap-2">
                    @error('message')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <div class="flex gap-2">
                        <input
                            wire:model.blur="message"
                            type="text"
                            placeholder="{{ config('agentic-chat-bubble.ui.placeholder', 'Type your message...') }}"
                            class="{{ $errors->has('message') ? 'border-red-600' : 'border-neutral-800' }} {{ $errors->has('message') ? 'focus:ring-red-600' : 'focus:ring-neutral-800' }} flex-1 border-2 px-3 py-2 focus:ring-2 focus:ring-offset-2 focus:outline-none"
                        />
                        <button
                            type="submit"
                            class="bg-neutral-900 px-4 py-2 font-bold text-white transition-colors hover:bg-neutral-700"
                        >
                            Send
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Chat Bubble --}}
        <button
            @click="open = !open"
            class="flex h-14 w-14 items-center justify-center bg-neutral-900 text-white shadow-lg transition-colors hover:bg-neutral-700"
            aria-label="Open chat"
        >
            <svg
                x-show="!open"
                xmlns="http://www.w3.org/2000/svg"
                class="h-6 w-6"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                />
            </svg>
            <svg
                x-show="open"
                xmlns="http://www.w3.org/2000/svg"
                class="h-6 w-6"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                style="display: none"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</div>
