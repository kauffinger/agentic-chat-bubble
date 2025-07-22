<?php

namespace Kauffinger\AgenticChatBubble\Tools;

use Kauffinger\AgenticChatBubble\Services\StatamicSearchToolService;
use Prism\Prism\Tool;
use Statamic\Facades\YAML;

class StatamicSearchTool extends Tool
{
    public function __construct(private readonly StatamicSearchToolService $service)
    {
        $this
            ->as('search')
            ->for('Useful for searching in publicly available information for the website. You can specify an index to search in in order to improve results.')
            ->withStringParameter('query', 'Search query like you would enter into Meiliserach.')
            ->withStringParameter('index', 'Index to search in, defaults to "default".', false)
            ->using($this);
    }

    public function __invoke(string $query, ?string $index = null): string
    {
        return YAML::dump($this->service->search($query, $index));
    }
}
