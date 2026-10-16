<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Warashi;

use JOOservices\CrawlerX\Adapters\Warashi\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PerformerDetailTest extends TestCase
{
    private const URL = 'https://warashi-asian-pornstars.fr/en/asian-female-pornstar/321/';

    public function test_a_full_profile_is_mapped_to_performer_fields(): void
    {
        $html = '<html><body><div id="pornostar-profil">'
            . '<h1><span itemprop="name">Rio Sample</span> <span itemprop="additionalName">リオ</span></h1>'
            . '<div id="pornostar-profil-photos-0"><img itemprop="image" src="/photos/rio.jpg"></div>'
            . '<ul id="pornostar-profil-noms-alternatifs">'
            . '<li><span itemprop="additionalName">Rio S.</span></li>'
            . '<li><span itemprop="additionalName">  </span></li>'
            . '<li>no western name</li>'
            . '</ul>'
            . '<p class="implode-tags"><a>Idol</a><a> </a><a>Model</a></p>'
            . '</div>'
            . '<div id="pornostar-profil-infos">'
            . '<time itemprop="birthDate" content="1990-01-02">January 2</time>'
            . '<span itemprop="addressCountry">Japan</span>'
            . '<span itemprop="value">158</span>'
            . '<p>Astrological sign: Capricorn</p>'
            . '<p>Measurements: JP 85-58-86</p>'
            . '<p>Cup size: D</p>'
            . '<p>Blood type: A</p>'
            . '<p>Current age: 34 years old</p>'
            . '<p>Porn/AV activity: 2012 - 2020</p>'
            . '</div></body></html>';

        $performer = $this->detail($html)->meta['performer'];

        self::assertSame('321', $performer['external_id']);
        self::assertSame('Rio Sample', $performer['name']);
        self::assertSame('リオ', $performer['name_japanese']);
        self::assertSame('https://warashi-asian-pornstars.fr/photos/rio.jpg', $performer['profile_image_url']);
        self::assertSame('1990-01-02', $performer['birth_date_raw']);
        self::assertSame('158 cm', $performer['height_raw']);
        self::assertSame('B85(D) W58 H86', $performer['size_raw']);
        self::assertSame(['リオ', 'Rio S.'], $performer['aliases']);
        self::assertSame(['Idol', 'Model'], $performer['tags']);
        self::assertSame('Japan', $performer['metadata']['birthplace']);
        self::assertSame('A', $performer['metadata']['blood_type']);
        self::assertSame('34', $performer['metadata']['age']);
        self::assertSame('Capricorn', $performer['metadata']['zodiac_sign']);
        self::assertSame('2012 - 2020', $performer['metadata']['debut_date_raw']);
    }

    /**
     * @return iterable<string, array{?string, ?string, ?string}>
     */
    public static function sizeCases(): iterable
    {
        yield 'measurements and cup' => ['Measurements: JP 80-55-82', 'Cup size: C', 'B80(C) W55 H82'];
        yield 'measurements without cup' => ['Measurements: JP 80 - 55 - 82', null, 'B80 W55 H82'];
        yield 'free text measurements are kept' => ['Measurements: 90-60-90', null, '90-60-90'];
        yield 'cup only' => [null, 'Cup size: E', 'B00(E)'];
        yield 'nothing known' => [null, null, null];
    }

    #[DataProvider('sizeCases')]
    public function test_size_is_built_from_measurements_and_cup(?string $measurements, ?string $cup, ?string $expected): void
    {
        $info = '<time itemprop="birthDate" content="1990-01-02">x</time>'
            . ($measurements !== null ? "<p>{$measurements}</p>" : '')
            . ($cup !== null ? "<p>{$cup}</p>" : '');
        $html = $this->minimal($info);

        $performer = $this->detail($html)->meta['performer'];

        self::assertSame($expected, $performer['size_raw']);
    }

    public function test_placeholder_values_are_treated_as_unknown(): void
    {
        $info = '<time itemprop="birthDate"> ? </time>'
            . '<span itemprop="value">tall</span>'
            . '<p>Blood type: unknown</p><p>Current age: N/A</p><p>Astrological sign: -</p>';

        $performer = $this->detail($this->minimal($info))->meta['performer'];

        self::assertNull($performer['birth_date_raw']);
        self::assertNull($performer['height_raw']);
        self::assertArrayNotHasKey('blood_type', $performer['metadata']);
        self::assertArrayNotHasKey('age', $performer['metadata']);
        self::assertArrayNotHasKey('zodiac_sign', $performer['metadata']);
    }

    public function test_birth_date_text_is_used_when_the_time_element_has_no_content_attribute(): void
    {
        $performer = $this->detail($this->minimal('<time itemprop="birthDate">March 3, 1991</time>'))->meta['performer'];

        self::assertSame('March 3, 1991', $performer['birth_date_raw']);
    }

    public function test_the_whole_page_is_the_profile_when_the_info_block_is_missing(): void
    {
        $html = '<html><body><div id="pornostar-profil"><h1><span itemprop="name">Solo</span></h1></div>'
            . '<p>Blood type: B</p></body></html>';

        $performer = $this->detail($html)->meta['performer'];

        self::assertSame('B', $performer['metadata']['blood_type']);
        self::assertNull($performer['profile_image_url']);
    }

    public function test_a_url_outside_the_profile_path_is_rejected_for_lack_of_an_external_id(): void
    {
        $this->expectException(\JOOservices\CrawlerX\Exceptions\CrawlParseException::class);
        $this->expectExceptionMessage('missing a valid external ID');

        (new PerformerDetail($this->clientWithHtml($this->minimal(''))))
            ->execute(new CrawlRequestDto(url: 'https://warashi-asian-pornstars.fr/en/other', type: CrawlType::PerformerDetail));
    }

    public function test_a_page_without_the_name_element_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Performer name element not found.');

        $this->detail('<html><body><div id="pornostar-profil"></div></body></html>');
    }

    public function test_a_blocked_challenge_and_an_empty_page_are_rejected_with_a_clear_reason(): void
    {
        foreach ([['<html>x</html>', 404, 'blocked or unavailable'], ['<html>Attention Required!</html>', 200, 'blocked or unavailable'], ['  ', 200, 'is empty']] as [$html, $status, $message]) {
            try {
                (new PerformerDetail($this->clientWithHtml($html, $status)))
                    ->execute(new CrawlRequestDto(url: self::URL, type: CrawlType::PerformerDetail));
                self::fail('Expected a RuntimeException for ' . $message);
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString($message, $exception->getMessage());
            }
        }
    }

    private function minimal(string $infoInner): string
    {
        return '<html><body><div id="pornostar-profil"><h1><span itemprop="name">Name</span></h1></div>'
            . '<div id="pornostar-profil-infos">' . $infoInner . '</div></body></html>';
    }

    private function detail(string $html): CrawlItemResultDto
    {
        return (new PerformerDetail($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: self::URL, type: CrawlType::PerformerDetail));
    }
}
