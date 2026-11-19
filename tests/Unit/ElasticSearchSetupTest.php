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

    private function indexerWithOpenPointInTime(int $closeStatus): ElasticSearch
    {
        $client = Mockery::mock();
        $client->shouldReceive('openPointInTime')->andReturn(['id' => 'pit-1']);
        $client->shouldReceive('closePointInTime')->once()->andThrow(new ClientResponseException('closing', $closeStatus));
        ElasticSearchClient::swap($client);

        $indexer = new ElasticSearch;
        $indexer->openPointInTime();

        return $indexer;
    }

    public function test_an_expired_point_in_time_is_not_an_error_when_the_indexer_is_destroyed(): void
    {
        $indexer = $this->indexerWithOpenPointInTime(404);

        unset($indexer); // __destruct closes the point in time

        $this->addToAssertionCount(1); // reaching here means the 404 was swallowed
    }

    public function test_other_errors_closing_a_point_in_time_still_surface(): void
    {
        $indexer = $this->indexerWithOpenPointInTime(500);

        $this->expectException(ClientResponseException::class);

        unset($indexer);
    }
}
