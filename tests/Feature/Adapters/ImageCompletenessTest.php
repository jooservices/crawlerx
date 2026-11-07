<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature\Adapters;

use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageCompletenessTest as UnitImageCompletenessTest;

/** Runs the image completeness assertions in the Feature suite too, so the adapter parsers count toward its coverage. */
final class ImageCompletenessTest extends UnitImageCompletenessTest
{
}
