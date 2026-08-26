<?php

declare(strict_types=1);

namespace App\Content\Application;

final class MarkdownRenderer
{
    public function render(string $markdown): string
    {
        $lines = preg_split('/\R/', trim($markdown)) ?: [];
        $html = [];
        /** @var list<string> $paragraph */
        $paragraph = [];
        $inList = false;

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
                if ($inList) {
                    $html[] = '</ul>';
                    $inList = false;
                }
                continue;
            }
            if (preg_match('/^(#{2,3})\s+(.+)$/', $trimmed, $heading) === 1) {
                $flushParagraph();
                if ($inList) {
                    $html[] = '</ul>';
                    $inList = false;
                }
                $level = strlen($heading[1]);
                $html[] = sprintf('<h%d>%s</h%d>', $level, $this->inline($heading[2]), $level);
                continue;
            }
            if (preg_match('/^-\s+(.+)$/', $trimmed, $item) === 1) {
                $flushParagraph();
                if (!$inList) {
                    $html[] = '<ul>';
                    $inList = true;
                }
                $html[] = '<li>' . $this->inline($item[1]) . '</li>';
                continue;
            }
            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }
            $paragraph[] = $trimmed;
        }
        $flushParagraph();
        if ($inList) {
            $html[] = '</ul>';
        }

        return implode("\n", $html);
    }

    private function inline(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?? $escaped;

        return preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^\s)]+|\/[^\s)]*)\)/',
            static fn (array $match): string => sprintf('<a href="%s">%s</a>', $match[2], $match[1]),
            $escaped
        ) ?? $escaped;
    }
}
