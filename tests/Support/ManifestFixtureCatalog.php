<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Support;

use JOOservices\CrawlerX\Dto\FixtureSampleDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;

final class ManifestFixtureCatalog
{
    /** @var list<string> */
    private const SKIP_SAMPLES = [];

    /**
     * @return list<array{slug: string, sample: FixtureSampleDto, fixturePath: string}>
     */
    public static function executableSamples(): array
    {
        $manifests = new FileAdapterManifestRegistry();
        $samples = [];

        foreach ($manifests->slugs() as $slug) {
            $manifest = $manifests->get($slug);
            if ($manifest === null) {
                continue;
            }

            foreach ($manifest->fixtureSamples as $sample) {
                if (in_array($sample->type, ['supporting', 'taxonomy'], true)) {
                    continue;
                }

                $key = $slug . ':' . $sample->type . ':' . $sample->name;
                if (in_array($key, self::SKIP_SAMPLES, true)) {
                    continue;
                }

                $fixturePath = self::fixturePath($slug, $sample->name);
                if (! is_file($fixturePath)) {
                    continue;
                }

                $samples[] = [
                    'slug' => $slug,
                    'sample' => $sample,
                    'fixturePath' => $fixturePath,
                ];
            }
        }

        return $samples;
    }

    public static function fixturePath(string $slug, string $fileName): string
    {
        return __DIR__ . '/../Fixtures/' . self::fixtureRelativePath($slug, $fileName);
    }

    public static function fixtureRelativePath(string $slug, string $fileName): string
    {
        return self::fixtureFolder($slug) . '/' . ltrim($fileName, '/');
    }

    public static function fixtureFolder(string $slug): string
    {
        return match ($slug) {
            'aisex' => 'Aisex',
            'avfan' => 'Avfan',
            'avfan_profiles' => 'AvfanProfiles',
            'avjoho' => 'avjoho',
            'javlibrary' => 'JavLibrary',
            default => $slug,
        };
    }

    /**
     * Registers the manifest-declared follow-up pages (`followUps`) of one sample.
     */
    public static function registerFollowUps(string $slug, FixtureSampleDto $sample): void
    {
        foreach ($sample->followUps as $followUp) {
            FixtureResponder::for('GET', $followUp->url)->file(self::fixtureRelativePath($slug, $followUp->name));
        }
    }

    public static function registerSupportingResponses(string $slug): void
    {
        $manifest = (new FileAdapterManifestRegistry())->get($slug);
        if ($manifest === null) {
            return;
        }

        foreach ($manifest->fixtureSamples as $sample) {
            if (! in_array($sample->type, ['supporting', 'taxonomy'], true)) {
                continue;
            }

            FixtureResponder::for('GET', $sample->url)->file(self::fixtureRelativePath($slug, $sample->name));
        }
    }

    public static function crawlTypeFromSample(FixtureSampleDto $sample): CrawlType
    {
        return match ($sample->type) {
            'detail' => CrawlType::Detail,
            'gallery' => CrawlType::Gallery,
            'performer_listing' => CrawlType::PerformerListing,
            'performer_detail' => CrawlType::PerformerDetail,
            default => CrawlType::Listing,
        };
    }
}
