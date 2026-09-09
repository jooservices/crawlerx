#!/usr/bin/env bash
set -euo pipefail

ARCHIVE="/Users/vietvu/Sites/archives/JOOservices.2/archive/src/XCrawlerII/backend/Modules/Crawler"
CORE="/Users/vietvu/Sites/archives/JOOservices.2/archive/src/XCrawlerII/backend/Modules/Core"
DEST="/Users/vietvu/Sites/JOOservices/crawlerx"
SRC="${DEST}/src"
TESTS="${DEST}/tests"

rm -rf "${SRC}" "${TESTS}/Unit" "${TESTS}/Feature" "${TESTS}/Fixtures"
mkdir -p "${SRC}" "${TESTS}/Unit" "${TESTS}/Feature" "${TESTS}/Support"

# Portable app layers
rsync -a \
  --exclude='AdapterCrawlExecutorAdapter.php' \
  --exclude='CrawlDebugRunnerAdapter.php' \
  --exclude='CrawlThrottleStateReaderAdapter.php' \
  --exclude='SiteFetchMethodResolverAdapter.php' \
  --exclude='SiteHealthProbeAdapter.php' \
  --exclude='SiteHealthStateReaderAdapter.php' \
  "${ARCHIVE}/app/Adapters/" "${SRC}/Adapters/"

rsync -a "${ARCHIVE}/app/Contracts/" "${SRC}/Contracts/"
rsync -a "${ARCHIVE}/app/Enums/CrawlType.php" "${SRC}/Enums/"
rsync -a "${ARCHIVE}/app/Exceptions/AdapterNotFoundException.php" "${SRC}/Exceptions/"
rsync -a "${ARCHIVE}/app/Registry/" "${SRC}/Registry/"
rsync -a \
  "${ARCHIVE}/app/Services/AdapterExecutor.php" \
  "${ARCHIVE}/app/Services/CrawlerXService.php" \
  "${DEST}/src/Services/" 2>/dev/null || mkdir -p "${SRC}/Services"

mkdir -p "${SRC}/Services"
cp "${ARCHIVE}/app/Services/AdapterExecutor.php" "${SRC}/Services/"
cp "${ARCHIVE}/app/Services/CrawlerXService.php" "${SRC}/Services/"

rsync -a \
  "${ARCHIVE}/app/Support/CodeNormalizer.php" \
  "${ARCHIVE}/app/Support/QueryPageUrl.php" \
  "${ARCHIVE}/app/Support/SizeParser.php" \
  "${ARCHIVE}/app/Support/JableStreamParser.php" \
  "${SRC}/Support/"

# DTOs
mkdir -p "${SRC}/Dto"
for f in RequestDto.php ListDto.php PaginationDto.php UrlDetectionResult.php; do
  cp "${ARCHIVE}/app/Dto/${f}" "${SRC}/Dto/"
done

# Core manifest DTO
cp "${CORE}/app/Dto/Crawler/AdapterManifestDto.php" "${SRC}/Dto/"
cp "${CORE}/app/Dto/Crawler/FixtureSampleDto.php" "${SRC}/Dto/" 2>/dev/null || true

# Tests and fixtures
rsync -a "${ARCHIVE}/tests/Unit/" "${TESTS}/Unit/"
rsync -a "${ARCHIVE}/tests/Fixtures/" "${TESTS}/Fixtures/"
cp "${ARCHIVE}/tests/TestCase.php" "${TESTS}/"

# Adapter-local fixtures
find "${ARCHIVE}/app/Adapters" -type d -name fixtures | while read -r dir; do
  site=$(basename "$(dirname "$dir")")
  mkdir -p "${TESTS}/Fixtures/${site}"
  rsync -a "${dir}/" "${TESTS}/Fixtures/${site}/"
done

# Namespace + renames
find "${SRC}" "${TESTS}" -name '*.php' -print0 | while IFS= read -r -d '' file; do
  sed -i '' \
    -e 's/Modules\\Crawler\\/JOOservices\\CrawlerX\\/g' \
    -e 's/Modules\\Core\\Dto\\Crawler\\/JOOservices\\CrawlerX\\Dto\\/g' \
    -e 's/Modules\\Core\\Dto\\CrawledItemDto/JOOservices\\CrawlerX\\Dto\\CrawlItemResultDto/g' \
    -e 's/use Modules\\Core\\Dto\\Crawler\\AdapterManifestDto/use JOOservices\\CrawlerX\\Dto\\AdapterManifestDto/g' \
    -e 's/JOOservices\\Client\\Contracts\\HttpClientInterface/JOOservices\\CrawlerX\\Contracts\\CrawlHttpClient/g' \
    -e 's/HttpClientInterface/CrawlHttpClient/g' \
    -e 's/\bRequestDto\b/CrawlRequestDto/g' \
    -e 's/\bListDto\b/CrawlListResultDto/g' \
    -e 's/\bItemDto\b/CrawlItemResultDto/g' \
    -e 's/\bPaginationDto\b/CrawlPaginationDto/g' \
    -e 's/use Illuminate\\Container\\Container;//g' \
    -e 's/use Illuminate\\Contracts\\Container\\Container as ContainerContract;//g' \
    "$file"
done

echo "Port complete: ${SRC}"
