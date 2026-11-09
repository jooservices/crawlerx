<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Guard;

final readonly class RobotsTxt
{
    /**
     * @param  list<RobotsTxtGroup>  $groups
     */
    public function __construct(
        public array $groups = [],
    ) {
    }

    public function allows(string $path, string $userAgent): bool
    {
        return $this->blockingRule($path, $userAgent) === null;
    }

    public function blockingRule(string $path, string $userAgent): ?RobotsTxtRule
    {
        return $this->groupFor($userAgent)?->blockingRule($path);
    }

    public function crawlDelay(string $userAgent): ?float
    {
        return $this->groupFor($userAgent)?->crawlDelay;
    }

    private function groupFor(string $userAgent): ?RobotsTxtGroup
    {
        $lower = strtolower($userAgent);
        $best = null;
        $bestLength = -1;

        foreach ($this->groups as $group) {
            foreach ($group->userAgents as $token) {
                if ($token === '*') {
                    continue;
                }

                $length = strlen($token);
                if ($length > $bestLength && str_contains($lower, strtolower($token))) {
                    $best = $group;
                    $bestLength = $length;
                }
            }
        }

        if ($best !== null) {
            return $best;
        }

        foreach ($this->groups as $group) {
            if (in_array('*', $group->userAgents, true)) {
                return $group;
            }
        }

        return null;
    }
}
