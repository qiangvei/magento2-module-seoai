<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Model\Meta;

use Qiangvei\SeoAi\Helper\Config;

/**
 * Minimal meta-template rendering engine.
 *
 * Replaces {{key}} placeholders in a template with supplied values,
 * strips HTML, collapses whitespace and truncates to SEO-friendly
 * lengths (title <= 60 chars before the store suffix is merged;
 * description <= 160 chars).
 */
class TemplateEngine
{
    /** Max characters for a rendered meta title (excluding title suffix). */
    private const TITLE_MAX_LENGTH = 60;

    /** Max characters for a rendered meta description. */
    private const DESCRIPTION_MAX_LENGTH = 160;

    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Render a template string with the given variables.
     *
     * @param string $template  Raw template, e.g. "{{name}} - {{brand}}"
     * @param array<string, string> $variables
     * @return string Cleaned, truncated value (title suffix NOT appended).
     */
    public function render(string $template, array $variables): string
    {
        $rendered = (string) preg_replace_callback(
            // Support both {{key}} (documented) and {key} (legacy config defaults).
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}|\{\s*([a-zA-Z0-9_]+)\s*\}/',
            static function (array $m) use ($variables): string {
                $key = $m[1] !== '' ? $m[1] : $m[2];

                return (string) ($variables[$key] ?? '');
            },
            $template
        );

        $rendered = $this->sanitize($rendered);

        // Fallback: a fully-empty render collapses to empty string.
        return $rendered;
    }

    /**
     * Render a meta title: template + optional store title suffix, truncated
     * so that the whole value stays within a reasonable SEO length.
     */
    public function renderTitle(string $template, array $variables, ?string $store = null): string
    {
        $body = $this->render($template, $variables);
        $suffix = $this->config->getTitleSuffix($store);

        if ($body === '') {
            return $this->sanitize($suffix);
        }

        if ($suffix !== '' && !str_ends_with($body, $suffix)) {
            $separator = str_starts_with($suffix, ' ') ? '' : ' ';
            $available = self::TITLE_MAX_LENGTH - strlen($separator . $suffix);
            if ($available > 0 && mb_strlen($body) > $available) {
                $body = $this->truncate($body, $available);
            }
            $body = $body . $separator . $suffix;
        } elseif (mb_strlen($body) > self::TITLE_MAX_LENGTH) {
            $body = $this->truncate($body, self::TITLE_MAX_LENGTH);
        }

        return $body;
    }

    /**
     * Render a meta description, truncated to <= 160 characters.
     */
    public function renderDescription(string $template, array $variables): string
    {
        $body = $this->render($template, $variables);

        if (mb_strlen($body) > self::DESCRIPTION_MAX_LENGTH) {
            $body = $this->truncate($body, self::DESCRIPTION_MAX_LENGTH);
        }

        return $body;
    }

    /**
     * Strip tags, decode entities and collapse whitespace.
     */
    private function sanitize(string $value): string
    {
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    /**
     * Truncate on a word boundary, appending an ellipsis when truncated.
     */
    private function truncate(string $value, int $maxLength): string
    {
        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        $cut = mb_substr($value, 0, max(0, $maxLength - 1));
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > (int) ($maxLength * 0.5)) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, " ,;:.!?\-") . '…';
    }
}
