<?php

use Kauffinger\AgenticChatBubble\Livewire\ChatBubbleComponent;

use function Pest\Livewire\livewire;

it('returns a successful response', function () {
    
  livewire(ChatBubbleComponent::class)
    ->assertSuccessful();
});
