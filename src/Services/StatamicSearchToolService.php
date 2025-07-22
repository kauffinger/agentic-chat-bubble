<?php

namespace Kauffinger\AgenticChatBubble\Services;

use Statamic\Contracts\Search\Result;
use Statamic\Data\DataCollection;
use Statamic\Facades\Search;

class StatamicSearchToolService
{
    public function __construct() {}

    public function getAvailableIndexes()
    {
        return Search::indexes();
    }

    public function search(string $query, ?string $index = 'default'): array
    {
        $index = Search::index($index);
        $fields = collect(data_get($index->config(), 'fields', []));

        /* @var DataCollection<Result> $results */
        $results = $index->search($query)->get();

        return $results->map(function (Result $result) use ($fields) {
            $data = [];
            $result = $result->getSearchable();

            /* @phpstan-ignore method.notFound */
            $data['id'] = $result->id();

            $fields->each(function (string $field) use (&$data, $result) {
                $fieldData = $result->getSearchValue($field);
                if ($fieldData !== null) {
                    $data[$field] = $fieldData;
                }
            });

            return $data;
        })
            ->all();
    }
}
