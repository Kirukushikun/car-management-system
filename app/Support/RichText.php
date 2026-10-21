<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * The light formatting people can use in long text fields, stored as plain text with markers:
 * **bold**, *italic*, __underline__, and lines starting with "- " (bullets) or "1. " (numbered).
 *
 * toHtml() escapes everything first and only then turns the markers into tags, so nothing typed or
 * pasted can inject HTML. toPlain() drops the markers for CSV exports and emails.
 */
final class RichText
{
    public static function toHtml(?string $text): HtmlString
    {
        if ($text === null || trim($text) === '') {
            return new HtmlString('');
        }

        $html = '';
        $list = null;
        $pendingBreak = false;

        foreach (preg_split('/\R/u', $text) as $line) {
            $kind = match (true) {
                (bool) preg_match('/^\s*[-•]\s+(.*)$/u', $line, $match) => 'ul',
                (bool) preg_match('/^\s*\d+[.)]\s+(.*)$/u', $line, $match) => 'ol',
                default => null,
            };

            if ($list !== null && $kind !== $list) {
                $html .= "</{$list}>";
                $list = null;
                $pendingBreak = false;
            }

            if ($kind !== null) {
                if ($list === null) {
                    $html .= "<{$kind} class=\"rt-list\">";
                    $list = $kind;
                    $pendingBreak = false;
                }

                $html .= '<li>'.self::inline($match[1]).'</li>';

                continue;
            }

            $html .= ($pendingBreak ? '<br>' : '').self::inline($line);
            $pendingBreak = true;
        }

        if ($list !== null) {
            $html .= "</{$list}>";
        }

        return new HtmlString($html);
    }

    public static function toPlain(?string $text): string
    {
        return preg_replace(['/\*\*(.+?)\*\*/su', '/__(.+?)__/su', '/(?<![*\w])\*(?![\s*])(.+?)(?<![\s*])\*(?![*\w])/su'], '$1', (string) $text);
    }

    /**
     * Bold, underline and italic inside one line, on already-escaped text.
     */
    private static function inline(string $text): string
    {
        $escaped = e($text);
        $escaped = preg_replace('/\*\*(.+?)\*\*/su', '<strong>$1</strong>', $escaped);
        $escaped = preg_replace('/__(.+?)__/su', '<u>$1</u>', $escaped);

        return preg_replace('/(?<![*\w])\*(?![\s*])(.+?)(?<![\s*])\*(?![*\w])/su', '<em>$1</em>', $escaped);
    }
}
