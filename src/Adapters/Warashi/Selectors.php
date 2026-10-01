<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Warashi;

final class Selectors
{
    public const PERFORMER_LINKS = '.listing-pornostars figcaption a';

    public const PAGINATION_NAV = '.listing-navigation ul';

    public const PAGINATION_CURRENT = '.listing-navigation li.page-courante';

    public const PERFORMER_PROFILE = '#pornostar-profil';

    public const PERFORMER_NAME = '#pornostar-profil h1 span[itemprop="name"]';

    public const PERFORMER_NAME_JAPANESE = '#pornostar-profil h1 span[itemprop="additionalName"]';

    public const PERFORMER_IMAGE = '#pornostar-profil-photos-0 img[itemprop="image"]';

    public const PROFILE_INFO = '#pornostar-profil-infos';

    public const ALIAS_LIST = '#pornostar-profil-noms-alternatifs li';

    public const TAGS = 'p.implode-tags a';

    public const BIRTH_DATE = 'time[itemprop="birthDate"]';

    public const BIRTHPLACE = '#pornostar-profil-infos span[itemprop="addressCountry"]';

    public const HEIGHT_VALUE = '#pornostar-profil-infos span[itemprop="value"]';

    public const GALLERY_TITLE = '#main h1';

    public const GALLERY_PERFORMER_LINK = '#fiche-galerie-infos-pornostar p a[href*="/asian-female-pornstar/"], #fiche-galerie-infos-pornostar p a[href*="/asian-male-pornstar/"]';

    public const GALLERY_SOURCE_LINK = '#fiche-galerie-infos-credits a';

    public const GALLERY_PHOTOS = '#fiche-galerie-listing-photos a[href*="/large/"]';
}
