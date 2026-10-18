<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\JavLibrary;

use JOOservices\CrawlerX\Adapters\JavLibrary\Types\Detail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../Fixtures/JavLibrary/';

    public function test_a_rated_video_yields_rating_member_counts_and_linked_people(): void
    {
        $movie = $this->detail('detail-javme3tuqi-rated.html', 'javme3tuqi');

        self::assertSame('HMN-911', $movie['code']);
        self::assertSame(['abcfc'], array_column($movie['performers'], 'external_id'));
        self::assertSame(['Kasui Jun'], array_column($movie['performers'], 'name'));
        self::assertSame('https://www.javlibrary.com/en/vl_star.php?s=abcfc', $movie['performers'][0]['url']);

        $meta = $movie['metadata'];
        self::assertSame(['score' => 9.0, 'scale' => 10.0, 'votes' => null], $meta['rating']);
        self::assertSame(858, $meta['want_count']);
        self::assertSame(385, $meta['watched_count']);
        self::assertSame(445, $meta['owned_count']);
        self::assertSame('Akai Suisei', $meta['director']);
        self::assertSame('Honnaka', $meta['maker']);
        self::assertSame('Honnaka', $meta['label']);
        self::assertSame('https://www.javlibrary.com/en/vl_maker.php?m=ayfa', $meta['maker_url']);
        self::assertSame('https://www.javlibrary.com/en/vl_director.php?d=aatq', $meta['director_url']);
        self::assertSame('https://www.javlibrary.com/en/vl_label.php?l=k4tq', $meta['label_url']);
    }

    public function test_an_unrated_video_has_no_rating_but_keeps_the_rest(): void
    {
        $movie = $this->detail('detail-javme3rasy-live.html', 'javme3rasy');

        self::assertSame('KV-328', $movie['code']);
        self::assertSame('2026-08-28', $movie['date']);
        self::assertSame(109, $movie['duration']);
        self::assertArrayNotHasKey('rating', $movie['metadata']);
        self::assertSame(72, $movie['metadata']['want_count']);
        self::assertSame(7, $movie['metadata']['watched_count']);
        self::assertSame(4, $movie['metadata']['owned_count']);
        self::assertSame(['Maid', 'Solowork', 'Cum', 'Bunny Girl'], $movie['tags']);
    }

    /** @return array<string, mixed> */
    private function detail(string $fixture, string $id): array
    {
        return (new Detail($this->clientWithHtml((string) file_get_contents(self::FIXTURES . $fixture))))
            ->execute(new CrawlRequestDto(url: 'https://www.javlibrary.com/en/?v=' . $id, type: CrawlType::Detail))
            ->meta['movie'];
    }
}
