<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Bomb;

final class Selectors
{
    public const IDOL_PACK_COVER = 'img[width="500"][height="430"]';

    public const LISTING_PACK_COVERS = 'a[href*="idolindex/"] img[width="700"][height="400"], a[href*="idolindex/"] img[width="116"][height="150"]';

    public const PACK_NAME_IMAGE = 'img[src*="images/n_"]';

    public const PACK_NUMBER_IMAGE = 'img[src*="images/num"]';

    public const PACK_PREVIEW_STRIPS = 'a[href^="javascript:photodisp"] img[width="380"]';

    public const PACK_DESCRIPTION = '.idol_copy2, .idol_copy';

    public const PACK_CONTENTS = '.idol_contents, .top_contents, .top_contents_main';

    public const LISTING_PRICE_TEXT = '.top_price';

    public const LISTING_THUMBNAIL = 'a[href^="javascript:photodisp"] img[src$="_s.jpg"], a[href^="javascript:photodisp"] img[src$="_bs.jpg"]';

    public const LISTING_CATCHCOPY = 'img[src*="_c."]';

    public const LISTING_NEW_BADGE = 'img[alt="NEW"]';

    public const LISTING_WHATSNEW = '#top_whatsnew';

    public const FOOTER_COPYRIGHT = '.foot_copyright';

    public const META_KEYWORDS = 'meta[name="keywords"]';

    public const PAGE_TITLE = 'title';
}
