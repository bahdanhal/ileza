<?php

declare(strict_types=1);

namespace App\Content\Application;

use App\Content\Domain\BlogArticle;

interface BlogArticleRepository
{
    /** @return list<BlogArticle> */
    public function all(string $locale): array;

    public function find(string $locale, string $slug): ?BlogArticle;
}
