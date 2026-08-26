<?php

declare(strict_types=1);

namespace App\Market\Infrastructure\Security;

final readonly class SvgSanitizer
{
    private const array DISALLOWED_TAGS = [
        'script',
        'foreignobject',
        'iframe',
        'embed',
        'object',
        'use',
        'applet',
        'meta',
        'link',
        'base',
    ];

    private const string DANGEROUS_CSS_PATTERN =
        '/(expression\s*\(|javascript\s*:|behavior\s*:|@import|url\s*\(\s*["\']?(?:javascript:|data:|http:|\/\/))/i';

    /**
     * Sanitizes SVG content and returns clean XML, or null if the SVG is malformed or dangerous.
     */
    public function sanitize(string $svgContent): ?string
    {
        $trimmed = trim($svgContent);
        if ($trimmed === '' || !str_contains($trimmed, '<svg')) {
            return null;
        }

        $dom = new \DOMDocument();
        $flags = LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING;

        $loaded = @$dom->loadXML($trimmed, $flags);

        if (!$loaded || $dom->documentElement === null) {
            return null;
        }

        if (strtolower((string) $dom->documentElement->localName) !== 'svg') {
            return null;
        }

        $this->sanitizeNode($dom->documentElement);

        $cleanXml = $dom->saveXML($dom->documentElement);

        return $cleanXml !== false ? $cleanXml : null;
    }

    private function sanitizeNode(\DOMElement $element): void
    {
        $toRemove = [];

        // Check child elements
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $tagName = strtolower((string) $child->localName);
                if (in_array($tagName, self::DISALLOWED_TAGS, true)) {
                    $toRemove[] = $child;
                    continue;
                }

                if ($tagName === 'style') {
                    $styleContent = rawurldecode($child->textContent);
                    if (preg_match(self::DANGEROUS_CSS_PATTERN, $styleContent)) {
                        $toRemove[] = $child;
                        continue;
                    }
                }

                $this->sanitizeNode($child);
            }
        }

        foreach ($toRemove as $node) {
            $element->removeChild($node);
        }

        // Check attributes
        $attrsToRemove = [];
        foreach ($element->attributes as $attr) {
            $name = strtolower($attr->nodeName);
            $value = $attr->nodeValue ?? '';

            // Strip event handlers (onload, onerror, onclick, etc.)
            if (str_starts_with($name, 'on')) {
                $attrsToRemove[] = $attr->nodeName;
                continue;
            }

            // Strip dangerous schemes in URI attributes
            if (in_array($name, ['href', 'xlink:href', 'src', 'action', 'formaction'], true)) {
                $decoded = rawurldecode($value);
                if (preg_match('/^\s*(javascript|vbscript|data:\s*text\/html)/i', $decoded)) {
                    $attrsToRemove[] = $attr->nodeName;
                    continue;
                }
            }

            // Strip style attributes containing dangerous CSS expressions or imports
            if ($name === 'style') {
                $styleDecoded = rawurldecode($value);
                if (preg_match(self::DANGEROUS_CSS_PATTERN, $styleDecoded)) {
                    $attrsToRemove[] = $attr->nodeName;
                }
            }
        }

        foreach ($attrsToRemove as $attrName) {
            $element->removeAttribute($attrName);
        }
    }
}
