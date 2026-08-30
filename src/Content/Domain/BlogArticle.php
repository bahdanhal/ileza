<?php

declare(strict_types=1);

namespace App\Content\Domain;

final readonly class BlogArticle
{
    public function __construct(
        private string $locale,
        private string $slug,
        private string $alternateSlug,
        private string $title,
        private string $description,
        private string $bodyMarkdown,
        private \DateTimeImmutable $publishedAt,
        private \DateTimeImmutable $updatedAt,
        private ?int $id = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getAlternateSlug(): string
    {
        return $this->alternateSlug;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getBodyMarkdown(): string
    {
        return $this->bodyMarkdown;
    }

    public function getPublishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getReadingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags($this->bodyMarkdown)) / 220));
    }

    /** @return list<string> */
    public function getPriceSlugs(): array
    {
        preg_match_all('/\{\{\s*price\(["\']([a-z0-9-]+)["\']\)\s*\}\}/', $this->bodyMarkdown, $matches);

        return array_values(array_unique($matches[1]));
    }
}
