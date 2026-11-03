<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily;

use JOOservices\CrawlerX\Support\MovieMeta;

/** Explicit source inventory shared by list rows and detail records; see docs/onepondo-family.md. */
final class SourceFields
{
    /** @var list<string> Source keys already represented by the normalized movie fields. */
    public const NORMALIZED_KEYS = [
        'MovieID', 'Title', 'TitleEn', 'Desc', 'DescEn', 'Release', 'Duration',
        'AvgRating', 'UC', 'UCNAME', 'UCNAMEEn', 'Series', 'SeriesEn', 'SeriesID',
        'MovieThumb', 'ThumbUltra', 'Year', 'MetaMovieID', 'CanStream', 'HasGallery',
    ];

    /** @var array<string, string> Source key => metadata key; nested values retain their original shape. */
    public const METADATA_KEYS = [
        'Actor' => 'actor',
        'ActorID' => 'actor_ids',
        'ActressesJa' => 'actresses_ja',
        'ActressesEn' => 'actresses_en',
        'ActressesList' => 'actresses_list',
        'Conditions' => 'conditions',
        'Expire' => 'expires_at',
        'Gallery' => 'gallery',
        'HasMemberGalleryZip' => 'has_member_gallery_zip',
        'HasSampleGalleryZip' => 'has_sample_gallery_zip',
        'IsTicketOnly' => 'is_ticket_only',
        'MemberFiles' => 'member_files',
        'MovieSeq' => 'movie_seq',
        'PPVPrice' => 'ppv_price',
        'RealMetaMovieID' => 'real_meta_movie_id',
        'SampleFiles' => 'sample_files',
        'SiteID' => 'site_id',
        'Status' => 'status',
        'ThumbHigh' => 'thumbnail_high_url',
        'ThumbLow' => 'thumbnail_low_url',
        'ThumbMed' => 'thumbnail_medium_url',
        'Type' => 'type',
        'UcNameList' => 'category_names',
    ];

    /** @var array<string, string> Only presentation/technical controls may be ignored. */
    public const IGNORED_KEYS = [
        'AffZip' => 'Affiliate ZIP presentation flag; not movie content.',
        'HasFlash' => 'Obsolete Flash player presentation flag.',
        'NoListDisplay' => 'Site listing visibility control.',
        'SampleExcludeFlag' => 'Site sample display control.',
    ];

    /**
     * Preserve explicitly present nulls, empty values, false and zero, including nested file/category properties.
     * Missing keys stay absent. Existing normalized fields remain untouched.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function metadata(array $row): array
    {
        $metadata = [];
        foreach (self::METADATA_KEYS as $source => $destination) {
            if (array_key_exists($source, $row)) {
                $metadata[$destination] = $source === 'Expire'
                    ? MovieMeta::isoDate(is_string($row[$source]) ? $row[$source] : null)
                    : $row[$source];
            }
        }

        return $metadata;
    }
}
