<?php

namespace Kauffinger\AgenticChatBubble\Tools;

use Kauffinger\AgenticChatBubble\Services\StatamicSearchToolService;
use Prism\Prism\Tool;
use Statamic\Facades\YAML;

class GetAvailableStatamicIndexesTool extends Tool
{
    public function __construct(private readonly StatamicSearchToolService $service)
    {
        $this
            ->as('retrieve_indexes')
            ->for('Retrieves the available Statamic search indexes.')
            ->using($this);
    }

    public function __invoke(): string
    {
        return YAML::dump($this->service->getAvailableIndexes());
    }
}
