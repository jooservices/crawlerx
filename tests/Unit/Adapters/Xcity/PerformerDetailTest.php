<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Xcity;

use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

final class PerformerDetailTest extends TestCase
{
    private const URL = 'https://xcity.jp/idol/detail/4321/';

    public function test_profile_fields_are_extracted_from_the_profile_list(): void
    {
        $html = '<html><body><h1>Yui Hatano All Titles Information</h1>'
            . '<div class="photo"><img class="actressThumb" src="/img/y.jpg"></div>'
            . '<dl class="profile">'
            . '<dd><span class="koumoku">Date of birth</span> 1988-05-24</dd>'
            . '<dd><span class="koumoku">Blood Type</span> Type ab</dd>'
            . '<dd><span class="koumoku">City of Born</span> Tokyo</dd>'
            . '<dd><span class="koumoku">Height</span> 163cm</dd>'
            . '<dd><span class="koumoku">Size</span> B84 W58 H85</dd>'
            . '<dd><span class="koumoku">Hobby</span> Reading</dd>'
            . '<dd><span class="koumoku">Special Skill</span> Piano</dd>'
            . '<dd><span class="koumoku">Other</span> note</dd>'
            . '<dd><span class="koumoku">★Favorite</span> 1234</dd>'
            . '<dd><span class="koumoku">Unknown label</span> ignored</dd>'
            . '<dd>no label cell</dd>'
            . '<dd><span class="koumoku">Hobby</span></dd>'
            . '</dl></body></html>';

        $performer = $this->detail($html)->meta['performer'];

        self::assertSame('4321', $performer['external_id']);
        self::assertSame('Yui Hatano', $performer['name']);
        self::assertSame('https://xcity.jp/img/y.jpg', $performer['profile_image_url']);
        self::assertSame('1988-05-24', $performer['birth_date_raw']);
        self::assertSame('AB', $performer['metadata']['blood_type']);
        self::assertSame('Tokyo', $performer['metadata']['birthplace']);
        self::assertSame('163cm', $performer['height_raw']);
        self::assertSame('B84 W58 H85', $performer['size_raw']);
        self::assertSame('Reading', $performer['metadata']['hobby']);
        self::assertSame('Piano', $performer['metadata']['special_skill']);
        self::assertSame('note', $performer['metadata']['other']);
        self::assertSame('1234', $performer['metadata']['favorite_count_raw']);
        self::assertArrayNotHasKey('unknown_label', $performer['metadata']);
    }

    public function test_blood_type_keeps_the_raw_text_when_no_group_is_recognised(): void
    {
        $html = '<html><body><h1>Name</h1><dl class="profile">'
            . '<dd><span class="koumoku">Blood Type</span> unknown</dd></dl></body></html>';

        self::assertSame('unknown', $this->detail($html)->meta['performer']['metadata']['blood_type']);
    }

    public function test_the_name_falls_back_through_the_heading_selectors_in_order(): void
    {
        $html = '<html><body><h1> </h1><h2>Second Heading</h2></body></html>';
        self::assertSame('Second Heading', $this->detail($html)->meta['performer']['name']);

        $html = '<html><body><div class="idol-name">Idol Name</div></body></html>';
        self::assertSame('Idol Name', $this->detail($html)->meta['performer']['name']);

        $html = '<html><body><p class="ttl">Title Class</p></body></html>';
        self::assertSame('Title Class', $this->detail($html)->meta['performer']['name']);
    }

    public function test_the_page_text_is_kept_as_raw_profile_when_there_is_no_profile_list(): void
    {
        $performer = $this->detail('<html><body><h1>Only Name</h1><p>extra  words</p></body></html>')->meta['performer'];

        self::assertSame('Only Name', $performer['name']);
        self::assertStringContainsString('extra words', $performer['raw_profile']['text']);
        self::assertNull($performer['profile_image_url']);
    }

    public function test_external_id_is_read_from_the_id_query_parameter(): void
    {
        $item = (new PerformerDetail($this->clientWithHtml('<html><body><h1>Q Name</h1></body></html>')))
            ->execute(new CrawlRequestDto(url: 'https://xcity.jp/idol/?id=99', type: CrawlType::PerformerDetail));

        self::assertSame('99', $item->meta['performer']['external_id']);
    }

    public function test_a_page_without_any_name_is_rejected(): void
    {
        $this->expectException(\Throwable::class);

        $this->detail('<html><body><div>nothing useful</div></body></html>');
    }

    public function test_a_blocked_response_is_reported_as_unavailable(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('xcity performer_detail page is blocked or unavailable.');

        (new PerformerDetail($this->clientWithHtml('<html></html>', 503)))
            ->execute(new CrawlRequestDto(url: self::URL, type: CrawlType::PerformerDetail));
    }

    private function detail(string $html): CrawlItemResultDto
    {
        return (new PerformerDetail($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: self::URL, type: CrawlType::PerformerDetail));
    }
}
