<?php

namespace Tests\Unit;

use App\Service\Indexer\ElasticSearch;
use Tests\TestCase;

class ElasticSearchPageFilterTest extends TestCase
{
    private function expectedFilter(): array
    {
        return [
            ['match' => ['doc_type' => 'page']],
            ['match' => ['path' => 'Sys/doc.pdf']],
        ];
    }

    public function test_fetch_all_pages_for_a_document_excludes_the_document_record(): void
    {
        \Elasticsearch::shouldReceive('search')
            ->once()
            ->withArgs(fn ($params) => $params['body']['query']['bool']['filter'] === $this->expectedFilter())
            ->andReturn(['hits' => ['hits' => []]]);

        (new ElasticSearch)->fetchAllPages('Sys/doc.pdf');
    }

    public function test_count_all_pages_for_a_document_excludes_the_document_record(): void
    {
        \Elasticsearch::shouldReceive('count')
            ->once()
            ->withArgs(fn ($params) => $params['body']['query']['bool']['filter'] === $this->expectedFilter())
            ->andReturn(['count' => 0]);

        (new ElasticSearch)->countAllPages('Sys/doc.pdf');
    }
}
