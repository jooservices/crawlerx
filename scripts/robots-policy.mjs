export function allowsRobotsPath(content, path, userAgent) {
    const groups = [];
    let agents = [];
    let rules = [];
    let hasDirectives = false;

    for (const rawLine of content.split(/\r?\n/)) {
        const line = rawLine.split('#', 1)[0].trim();
        if (line === '') {
            if (agents.length > 0) groups.push({ agents, rules });
            agents = [];
            rules = [];
            hasDirectives = false;
            continue;
        }

        const separator = line.indexOf(':');
        if (separator < 0) continue;
        const directive = line.slice(0, separator).trim().toLowerCase();
        const value = line.slice(separator + 1).trim();

        if (directive === 'user-agent') {
            if (hasDirectives) {
                groups.push({ agents, rules });
                agents = [];
                rules = [];
                hasDirectives = false;
            }
            if (value !== '') agents.push(value);
        } else if (agents.length > 0 && (directive === 'allow' || directive === 'disallow') && value !== '') {
            rules.push({ allow: directive === 'allow', pattern: value });
            hasDirectives = true;
        } else if (agents.length > 0 && directive === 'crawl-delay' && value !== '' && Number.isFinite(Number(value))) {
            hasDirectives = true;
        }
    }
    if (agents.length > 0) groups.push({ agents, rules });

    const token = String(userAgent ?? '').toLowerCase();
    let group = null;
    let bestAgentLength = -1;
    for (const candidate of groups) {
        for (const agent of candidate.agents) {
            if (agent !== '*' && token.includes(agent.toLowerCase()) && agent.length > bestAgentLength) {
                group = candidate;
                bestAgentLength = agent.length;
            }
        }
    }
    group ??= groups.find((candidate) => candidate.agents.includes('*')) ?? null;
    if (group === null) return true;

    let bestRule = null;
    for (const rule of group.rules) {
        if (!matchesPattern(rule.pattern, path)) continue;

        if (
            bestRule === null
            || rule.pattern.length > bestRule.pattern.length
            || (rule.pattern.length === bestRule.pattern.length && rule.allow)
        ) {
            bestRule = rule;
        }
    }

    return bestRule === null || bestRule.allow;
}

function matchesPattern(pattern, path) {
    const anchored = pattern.endsWith('$');
    const segments = (anchored ? pattern.slice(0, -1) : pattern).split('*');
    let position = 0;

    for (let index = 0; index < segments.length; index += 1) {
        const segment = segments[index];
        if (index === 0) {
            if (!path.startsWith(segment)) return false;
            position = segment.length;
            continue;
        }

        if (anchored && index === segments.length - 1) {
            const suffixPosition = path.length - segment.length;
            return suffixPosition >= position && path.endsWith(segment);
        }

        const found = path.indexOf(segment, position);
        if (found < 0) return false;
        position = found + segment.length;
    }

    return !anchored || position === path.length;
}
