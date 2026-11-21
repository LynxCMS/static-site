<?php

declare(strict_types=1);

namespace LynxCms\StaticSite\Export;

/**
 * Rewrites a page's links to its own site from the root (/about rather than https://site/about),
 * so one export works on any host: the live one, a preview and a local server. Addresses that
 * leave the page keep the full address: canonical and alternate links, meta tags (Open Graph)
 * and text outside tags, such as JSON-LD.
 *
 * @internal
 */
final readonly class RootRelativeLinks
{
    /** A tag, with quoted attribute values that may hold ">". */
    private const string TAG = '/<([a-zA-Z][\w:-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/';

    private const string ATTRIBUTE = '/(\s[^\s"\'=<>\/]+\s*=\s*)("[^"]*"|\'[^\']*\')/';

    private const string KEEPS_FULL_ADDRESS = '/\brel\s*=\s*["\']?[^"\'>]*\b(canonical|alternate)\b/i';

    private string $site;

    public function __construct(string $baseUrl)
    {
        $parts = parse_url($baseUrl);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $this->site = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').$port;
    }

    public function rewrite(string $html): string
    {
        return (string) preg_replace_callback(self::TAG, fn (array $tag): string => $this->keepsFullAddress($tag[1], $tag[2])
            ? $tag[0]
            : '<'.$tag[1].$this->attributes($tag[2]).'>', $html);
    }

    private function keepsFullAddress(string $name, string $attributes): bool
    {
        return strcasecmp($name, 'meta') === 0
            || (strcasecmp($name, 'link') === 0 && preg_match(self::KEEPS_FULL_ADDRESS, $attributes) === 1);
    }

    private function attributes(string $attributes): string
    {
        return (string) preg_replace_callback(self::ATTRIBUTE, function (array $attribute): string {
            $quote = $attribute[2][0];
            $value = substr($attribute[2], 1, -1);

            return $attribute[1].$quote.$this->fromRoot($value).$quote;
        }, $attributes);
    }

    /** Every address of this site in the value from its root; a lookalike host stays. */
    private function fromRoot(string $value): string
    {
        $pattern = '#'.preg_quote($this->site, '#').'(?![\w.:-])(/?)#i';

        return (string) preg_replace_callback($pattern, fn (): string => '/', $value);
    }
}
