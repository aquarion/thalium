<?php

namespace Tests\Unit;

use App\Facades\Elasticsearch as ElasticSearchClient;
use App\Service\Indexer\ElasticSearch;
use Mockery;
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

    // Elastic\Elasticsearch\Client is final, so swap in a plain double rather than mocking the facade
    public function test_fetch_all_pages_for_a_document_excludes_the_document_record(): void
    {
        $client = Mockery::mock();
        $client->shouldReceive('search')
            ->once()
            ->withArgs(fn ($params) => $params['body']['query']['bool']['filter'] === $this->expectedFilter())
            ->andReturn(['hits' => ['hits' => []]]);
        ElasticSearchClient::swap($client);

        (new ElasticSearch)->fetchAllPages('Sys/doc.pdf');
    }

    public function test_count_all_pages_for_a_document_excludes_the_document_record(): void
    {
        $client = Mockery::mock();
        $client->shouldReceive('count')
            ->once()
            ->withArgs(fn ($params) => $params['body']['query']['bool']['filter'] === $this->expectedFilter())
            ->andReturn(['count' => 0]);
        ElasticSearchClient::swap($client);

        (new ElasticSearch)->countAllPages('Sys/doc.pdf');
    }
}
