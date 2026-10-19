<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Jable;

use JOOservices\CrawlerX\Adapters\Jable\Types\Detail;
use JOOservices\CrawlerX\Adapters\Jable\Types\PerformerListing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../Fixtures/jable/';

    public function test_a_recent_video_yields_release_date_quality_counts_and_hashed_model_slugs(): void
    {
        $movie = $this->detail('detail-jur-832.html', 'jur-832');

        self::assertSame('JUR-832', $movie['code']);
        self::assertSame('2026-09-03', $movie['date']);
        self::assertSame(['木下凛々子'], array_column($movie['performers'], 'name'));
        self::assertSame('d966b7e1a0aba5a7c6f41782a06e010a', $movie['performers'][0]['external_id']);
        self::assertSame('https://en.jable.tv/models/d966b7e1a0aba5a7c6f41782a06e010a/', $movie['performers'][0]['url']);
        self::assertContains('Creampie', $movie['tags']);
        self::assertNotContains('木下凛々子', $movie['tags']);

        $meta = $movie['metadata'];
        self::assertSame('2026-09-03', $meta['release_date_raw']);
        self::assertSame('HD Original Video', $meta['quality_label']);
        self::assertSame('1 month ago', $meta['age_label']);
        self::assertSame(486384, $meta['views']);
        self::assertSame(2704, $meta['favorite_count']);
        self::assertSame(19, $meta['comment_count']);
        self::assertSame(['Roleplay'], $meta['categories']);
    }

    public function test_an_older_video_has_no_release_date_but_keeps_the_rest(): void
    {
        $movie = $this->detail('detail-fjin-091.html', 'fjin-091');

        self::assertNull($movie['date']);
        self::assertSame(['JULIA'], array_column($movie['performers'], 'name'));
        self::assertSame('julia', $movie['performers'][0]['external_id']);
        self::assertSame('Chinese Subtitle', $movie['metadata']['quality_label']);
        self::assertSame('3 years ago', $movie['metadata']['age_label']);
        self::assertSame(563578, $movie['metadata']['views']);
        self::assertSame(2637, $movie['metadata']['favorite_count']);
        self::assertSame(7, $movie['metadata']['comment_count']);
        self::assertSame('此作品曾在本站上傳，現已更新至中文字幕版。', $movie['description']);
    }

    public function test_the_models_index_follows_the_numbered_next_page(): void
    {
        $result = (new PerformerListing($this->clientWithHtml((string) file_get_contents(self::FIXTURES . 'models-listing.html'))))
            ->execute(new CrawlRequestDto(url: 'https://en.jable.tv/models/', type: CrawlType::PerformerListing));

        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame('https://en.jable.tv/models/2/', $result->pagination->nextUrl);
    }

    public function test_the_next_page_comes_from_the_page_shown_not_from_the_request_number(): void
    {
        $html = (string) file_get_contents(self::FIXTURES . 'models-listing.html');
        $page2 = str_replace(
            ['<span class="page-link active disabled">01</span>', '<a class="page-link" href="/models/2/" data-action="ajax" data-container-id="list_models_models_list_pagination" data-block-id="list_models_models_list" data-parameters="sort_by:avg_videos_popularity;from:02">02</a>'],
            ['<a class="page-link" href="/models/">01</a>', '<span class="page-link active disabled">02</span>'],
            $html,
        );

        // The request still says page 1 (a follow-up from a next url keeps the default).
        $result = (new PerformerListing($this->clientWithHtml($page2)))
            ->execute(new CrawlRequestDto(url: 'https://en.jable.tv/models/2/', type: CrawlType::PerformerListing, page: 1));

        self::assertSame('https://en.jable.tv/models/3/', $result->pagination->nextUrl);
        self::assertSame(2, $result->pagination->currentPage);
    }

    /** @return array<string, mixed> */
    private function detail(string $fixture, string $slug): array
    {
        return (new Detail($this->clientWithHtml((string) file_get_contents(self::FIXTURES . $fixture))))
            ->execute(new CrawlRequestDto(url: 'https://en.jable.tv/videos/' . $slug . '/', type: CrawlType::Detail))
            ->meta['movie'];
    }
}
