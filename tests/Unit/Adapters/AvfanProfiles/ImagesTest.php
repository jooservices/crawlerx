<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\AvfanProfiles;

use JOOservices\CrawlerX\Adapters\AvfanProfiles\Types\PerformerListing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImagesTest extends ImageAuditTestCase
{
    public function test_every_performer_card_keeps_its_portrait(): void
    {
        $result = $this->list(PerformerListing::class, 'AvfanProfiles/listing-cup-a.html', 'https://av-fan.tokyo/actress/cup/A.html', CrawlType::PerformerListing);
        $performers = $this->metaOf($result->items, 'performer');

        self::assertCount(104, $performers);
        self::assertCount(104, $this->column($performers, 'profile_image_url'));
        self::assertSame('http://pics.dmm.co.jp/mono/actjpgs/nagase_yui2.jpg', $performers[0]['profile_image_url']);
    }
}
