<?php

declare(strict_types=1);

namespace App\Content\Application;

final class MarkdownRenderer
{
    public function render(string $markdown): string
    {
        $lines = preg_split('/\R/u', trim($markdown)) ?: [];
        $html = [];
        /** @var list<string> $paragraph */
        $paragraph = [];
        /** @var 'ul'|'ol'|null $inList */
        $inList = null;

        $closeList = function () use (&$inList, &$html): void {
            if ($inList !== null) {
                $html[] = $inList === 'ol' ? '</ol>' : '</ul>';
                $inList = null;
            }
        };

        $flushParagraph = function () use (&$paragraph, &$html): void {
            if ($paragraph !== []) {
                $html[] = '<p>' . $this->inline(implode(' ', $paragraph)) . '</p>';
                $paragraph = [];
            }
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $flushParagraph();
                $closeList();
                continue;
            }
            if (preg_match('/^(#{2,3})\s+(.+)$/u', $trimmed, $heading) === 1) {
                $flushParagraph();
                $closeList();
                $level = strlen($heading[1]);
                $html[] = sprintf('<h%d>%s</h%d>', $level, $this->inline($heading[2]), $level);
                continue;
            }
            if (preg_match('/^!\[([^\]]*)\]\((https?:\/\/[^\s)]+|\/[^\s)]*)\)$/u', $trimmed, $image) === 1) {
                $flushParagraph();
                $closeList();
                $alt = htmlspecialchars($image[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $src = htmlspecialchars($image[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                if ($alt !== '') {
                    $html[] = sprintf(
                        '<figure class="blog-figure"><img src="%s" alt="%s" loading="lazy"><figcaption>%s</figcaption></figure>',
                        $src,
                        $alt,
                        $alt
                    );
                } else {
                    $html[] = sprintf('<figure class="blog-figure"><img src="%s" alt="" loading="lazy"></figure>', $src);
                }
                continue;
            }
            if (preg_match('/^-\s+(.+)$/u', $trimmed, $item) === 1) {
                $flushParagraph();
                if ($inList !== 'ul') {
                    $closeList();
                    $html[] = '<ul>';
                    $inList = 'ul';
                }
                $html[] = '<li>' . $this->inline($item[1]) . '</li>';
                continue;
            }
            if (preg_match('/^\d+\.\s+(.+)$/u', $trimmed, $item) === 1) {
                $flushParagraph();
                if ($inList !== 'ol') {
                    $closeList();
                    $html[] = '<ol>';
                    $inList = 'ol';
                }
                $html[] = '<li>' . $this->inline($item[1]) . '</li>';
                continue;
            }
            $closeList();
            $paragraph[] = $trimmed;
        }
        $flushParagraph();
        $closeList();

        return implode("\n", $html);
    }

    private function inline(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $escaped) ?? $escaped;

        $escaped = preg_replace_callback(
            '/!\[([^\]]*)\]\((https?:\/\/[^\s)]+|\/[^\s)]*)\)/u',
            static fn (array $match): string => sprintf('<img src="%s" alt="%s" loading="lazy">', $match[2], $match[1]),
            $escaped
        ) ?? $escaped;

        return preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^\s)]+|\/[^\s)]*)\)/u',
            static fn (array $match): string => sprintf('<a href="%s">%s</a>', $match[2], $match[1]),
            $escaped
        ) ?? $escaped;
    }
}
