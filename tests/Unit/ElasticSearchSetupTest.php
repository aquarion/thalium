<?php

namespace Tests\Unit;

use App\Facades\Elasticsearch as ElasticSearchClient;
use App\Service\Indexer\ElasticSearch;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ElasticSearchSetupTest extends TestCase
{
    private function swapClient(int $getPipelineStatus): MockInterface
    {
        $ingest = Mockery::mock();
        $ingest->shouldReceive('getPipeline')->andThrow(new ClientResponseException('denied', $getPipelineStatus));

        $indices = Mockery::mock();
        $indices->shouldReceive('putMapping')->once();

        $client = Mockery::mock();
        $client->shouldReceive('indices')->andReturn($indices);
        $client->shouldReceive('ingest')->andReturn($ingest);
        ElasticSearchClient::swap($client);

        return $ingest;
    }

    public function test_setup_carries_on_when_the_app_user_cannot_read_pipelines(): void
    {
        $ingest = $this->swapClient(403);
        $ingest->shouldNotReceive('putPipeline');

        (new ElasticSearch)->setup();

        $this->addToAssertionCount(1); // setup() did not throw; Mockery verifies putPipeline was not called
    }

    public function test_setup_creates_the_pipeline_when_it_is_missing(): void
    {
        $ingest = $this->swapClient(404);
        $ingest->shouldReceive('putPipeline')->once();

        (new ElasticSearch)->setup();

        $this->addToAssertionCount(1);
    }

    public function test_setup_rethrows_other_errors(): void
    {
        $this->swapClient(500);

        $this->expectException(ClientResponseException::class);

        (new ElasticSearch)->setup();
    }
}
