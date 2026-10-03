<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Services;

use JOOservices\CrawlerX\Dto\CrawlErrorDto;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\CrawlOutcomeDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\AdapterNotFoundException;
use JOOservices\CrawlerX\Exceptions\AmbiguousUrlException;
use JOOservices\CrawlerX\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerX\Exceptions\CrawlFetchException;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Exceptions\UnsupportedUrlException;
use Throwable;

final class CrawlRequestBuilder
{
    private ?string $site = null;

    private ?CrawlType $type = null;

    private ?int $page = null;

    private ?CrawlOptionsDto $options = null;

    private ?string $query = null;

    public function __construct(
        private readonly CrawlOrchestrator $orchestrator,
        private ?string $url = null,
    ) {
    }

    public function url(string $url): self
    {
        $clone = clone $this;
        $clone->url = $url;

        return $clone;
    }

    public function site(string $site): self
    {
        $clone = clone $this;
        $clone->site = $site;

        return $clone;
    }

    public function type(CrawlType $type): self
    {
        $clone = clone $this;
        $clone->type = $type;

        return $clone;
    }

    public function page(int $page): self
    {
        $clone = clone $this;
        $clone->page = $page;

        return $clone;
    }

    public function options(?CrawlOptionsDto $options): self
    {
        $clone = clone $this;
        $clone->options = $options;

        return $clone;
    }

    public function query(string $query): self
    {
        $clone = clone $this;
        $clone->query = $query;

        return $clone;
    }

    public function crawl(): CrawlListResultDto|CrawlItemResultDto
    {
        return $this->orchestrator->crawl(
            $this->url,
            $this->site,
            $this->type,
            $this->page,
            $this->options,
            $this->query,
        );
    }

    public function tryCrawl(): CrawlOutcomeDto
    {
        try {
            return CrawlOutcomeDto::success($this->crawl());
        } catch (UnsupportedUrlException $exception) {
            return $this->failure(CrawlErrorCode::UnsupportedUrl, $exception->getMessage());
        } catch (AmbiguousUrlException $exception) {
            return $this->failure(CrawlErrorCode::AmbiguousUrl, $exception->getMessage());
        } catch (AdapterNotFoundException $exception) {
            return $this->failure(CrawlErrorCode::AdapterNotFound, $exception->getMessage());
        } catch (CrawlFetchException $exception) {
            return $this->failure(
                $exception->errorCode,
                $exception->getMessage(),
                $exception->fetch,
                $exception->retryable,
                $exception->retryAfterSeconds,
            );
        } catch (CrawlBlockedException $exception) {
            return $this->failure(CrawlErrorCode::Blocked, $exception->getMessage(), $exception->fetch);
        } catch (CrawlParseException $exception) {
            return $this->failure(CrawlErrorCode::ParseFailed, $exception->getMessage());
        } catch (Throwable $exception) {
            return $this->failure(CrawlErrorCode::Unknown, $exception->getMessage());
        }
    }

    private function failure(
        CrawlErrorCode $code,
        string $message,
        ?\JOOservices\CrawlerX\Dto\FetchMetaDto $fetch = null,
        ?bool $retryable = null,
        ?int $retryAfterSeconds = null,
    ): CrawlOutcomeDto {
        return CrawlOutcomeDto::failure(new CrawlErrorDto(
            code: $code,
            message: $message,
            url: $this->url,
            fetch: $fetch,
            retryable: $retryable ?? $code->defaultRetryable(),
            retryAfterSeconds: $retryAfterSeconds,
        ));
    }
}
