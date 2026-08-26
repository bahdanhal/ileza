<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'blog_articles')]
#[ORM\UniqueConstraint(name: 'uniq_blog_locale_slug', columns: ['locale', 'slug'])]
#[ORM\Index(columns: ['locale', 'published_at'], name: 'idx_blog_locale_published')]
class BlogArticleEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 5)]
    private string $locale;

    #[ORM\Column(type: Types::STRING, length: 160)]
    private string $slug;

    #[ORM\Column(type: Types::STRING, length: 160)]
    private string $alternateSlug;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title;

    #[ORM\Column(type: Types::STRING, length: 512)]
    private string $description;

    #[ORM\Column(type: Types::TEXT)]
    private string $bodyMarkdown;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $publishedAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $locale,
        string $slug,
        string $alternateSlug,
        string $title,
        string $description,
        string $bodyMarkdown,
        \DateTimeImmutable $publishedAt,
        \DateTimeImmutable $updatedAt,
    ) {
        if (!in_array($locale, ['en', 'pl'], true)) {
            throw new \InvalidArgumentException('Blog article locale must be English or Polish.');
        }
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            throw new \InvalidArgumentException('Blog article slug must be URL-safe.');
        }
        $this->locale = $locale;
        $this->slug = $slug;
        $this->alternateSlug = $alternateSlug;
        $this->title = $title;
        $this->description = $description;
        $this->bodyMarkdown = $bodyMarkdown;
        $this->publishedAt = $publishedAt;
        $this->updatedAt = $updatedAt;
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
