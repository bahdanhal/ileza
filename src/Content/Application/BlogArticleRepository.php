<?php

declare(strict_types=1);

namespace App\Content\Application;

use App\Content\Domain\BlogArticle;
use App\Entity\BlogArticleEntity;

interface BlogArticleRepository
{
    /** @return list<BlogArticle> */
    public function all(string $locale): array;

    public function find(string $locale, string $slug): ?BlogArticle;

    /** @return list<BlogArticle> */
    public function findAllForAdmin(?string $locale = null): array;

    public function findEntity(int $id): ?BlogArticleEntity;

    public function findEntityByLocaleAndSlug(string $locale, string $slug): ?BlogArticleEntity;

    public function save(BlogArticleEntity $entity): void;

    public function delete(BlogArticleEntity $entity): void;
}
