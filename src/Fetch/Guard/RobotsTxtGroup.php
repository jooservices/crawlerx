<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Guard;

final readonly class RobotsTxtGroup
{
    /**
     * @param  list<string>  $userAgents
     * @param  list<RobotsTxtRule>  $rules
     */
    public function __construct(
        public array $userAgents,
        public array $rules,
        public ?float $crawlDelay,
    ) {
    }

    public function allows(string $path): bool
    {
        return $this->blockingRule($path) === null;
    }

    public function blockingRule(string $path): ?RobotsTxtRule
    {
        $best = null;
        $bestLength = -1;

        foreach ($this->rules as $rule) {
            if (! $rule->matches($path)) {
                continue;
            }

            $length = $rule->length();
            if ($length > $bestLength || ($length === $bestLength && $rule->allow)) {
                $best = $rule;
                $bestLength = $length;
            }
        }

        return $best !== null && ! $best->allow ? $best : null;
    }
}
