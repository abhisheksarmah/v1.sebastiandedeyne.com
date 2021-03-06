<?php

namespace App\Posts;

use Illuminate\Support\Collection;
use Spatie\Sheets\Sheets;
use Illuminate\Cache\Repository;

class PostRepository
{
    /** @var \Spatie\Sheets\Sheets */
    private $sheets;

    /** @var \Illuminate\Cache\Repository */
    private $cache;

    public function __construct(Sheets $sheets, Repository $cache)
    {
        $this->sheets = $sheets;

        $this->cache = $cache;
    }

    public function getAllPosts(): Collection
    {
        return $this->cache->rememberForever('posts.all', function () {
            return $this->sheets
                ->collection('posts')
                ->all()
                ->reject->draft
                ->sortByDesc('date');
        });
    }

    public function getAllArticles(): Collection
    {
        return $this->getAllPosts()
            ->reject(function (Post $post) {
                return $post->link;
            });
    }

    public function getRelatedPosts(Post $post): Collection
    {
        if ($post->tags->isEmpty()) {
            return collect();
        }

        return $this->getAllPosts()
            ->where('slug', '!==', $post->slug)
            ->map(function (Post $relatedPost) use ($post) {
                return data_set(
                    $relatedPost,
                    'equal_tags_count',
                    $this->intersectTags($post, $relatedPost)
                        ->count()
                );
            })
            ->where('equal_tags_count', '>', 0)
            ->sortByDesc('date')
            ->sortByDesc('equal_tags_count')
            ->take(3)
            ->values();
    }

    public function intersectTags(Post $post, Post ...$otherPosts): Collection
    {
        if (empty($otherPosts)) {
            return $post->tags;
        }

        return collect($otherPosts)
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->intersect($post->tags);
    }

    public function getMainFeedItems(): Collection
    {
        return $this->getAllPosts()->take(20);
    }

    public function getArticleFeedItems(): Collection
    {
        return $this->getAllArticles()->take(20);
    }
}
