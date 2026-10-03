<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature\Snapshots;

use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\FetchOptionsDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Tests\Support\ManifestFixtureCatalog;
use JOOservices\CrawlerX\Tools\Canary\RequiredFields;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/tools/canary/RequiredFields.php';

final class SnapshotParseTest extends TestCase
{
    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        parent::tearDown();
    }

    public function test_tc_sn_01_every_registered_fixture_parses_offline_with_required_fields(): void
    {
        $registry = new FileAdapterManifestRegistry();
        $responses = [];
        foreach ($registry->all() as $slug => $manifest) {
            foreach ($manifest->fixtureSamples as $sample) {
                $path = ManifestFixtureCatalog::fixturePath($slug, $sample->name);
                $body = file_get_contents($path);
                self::assertIsString($body);
                $responses[$sample->url] = $body;
            }
        }

        CrawlerXFactory::useFetchChain(new FetchFallbackChain([
            FetchMethod::Http->value => new SnapshotFetchHandler($responses),
        ]));

        foreach ($registry->all() as $slug => $manifest) {
            foreach ($manifest->fixtureSamples as $sample) {
                $result = CrawlerX::url($sample->url)
                    ->site($slug)
                    ->type($this->crawlType($sample->type))
                    ->options(new CrawlOptionsDto(fetch: new FetchOptionsDto(method: FetchMethod::Http, noFallback: true)))
                    ->crawl();
                $coverage = RequiredFields::check($result, $sample->type);

                self::assertSame([], $coverage['missing'], "{$slug}/{$sample->name}: missing fields");
            }
        }
    }

    public function test_tc_sn_02_fixture_metadata_marks_sanitized_captures_explicitly(): void
    {
        $registry = new FileAdapterManifestRegistry();
        foreach ($registry->all() as $slug => $manifest) {
            foreach ($manifest->fixtureSamples as $sample) {
                $metadataPath = ManifestFixtureCatalog::fixturePath($slug, $sample->name) . '.meta.json';
                $metadata = json_decode((string) file_get_contents($metadataPath), true, flags: JSON_THROW_ON_ERROR);
                self::assertArrayHasKey('sanitized', $metadata);
                self::assertIsBool($metadata['sanitized']);
            }
        }
    }

    private function crawlType(string $type): CrawlType
    {
        return match ($type) {
            'detail' => CrawlType::Detail,
            'gallery' => CrawlType::Gallery,
            'performer_listing' => CrawlType::PerformerListing,
            'performer_detail' => CrawlType::PerformerDetail,
            default => CrawlType::Listing,
        };
    }
}

/** @internal */
final class SnapshotFetchHandler implements FetchMethodHandler
{
    /** @param array<string, string> $responses */
    public function __construct(private readonly array $responses)
    {
    }

    public function supports(FetchMethod $method): bool
    {
        return $method === FetchMethod::Http;
    }

    public function fetch(
        string $url,
        SiteProfileDto $profile,
        FetchMethod $method,
        ?CrawlOptionsDto $options = null,
    ): FetchResultDto {
        if (! isset($this->responses[$url])) {
            throw new CrawlParseException("Snapshot is not registered for {$url}.");
        }

        return new FetchResultDto(
            ok: true,
            body: $this->responses[$url],
            status: 200,
            methodUsed: FetchMethod::Http,
            elapsedMs: 0,
            challengeDetected: false,
            finalUrl: $url,
        );
    }
}
