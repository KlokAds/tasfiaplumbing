<?php

namespace App\Models\Concerns;

use App\Models\Redirect;
use Illuminate\Support\Str;

/**
 * Slug + URL safety for any public page model.
 *
 * - Slug is generated once on create and never silently regenerated from the title.
 * - Changing a slug records a 301 from the old URL to the new one.
 * - Deleting a page records a 410 so the old URL is visible in the Redirect manager.
 * - content_updated_at only moves when real content changes (sitemap lastmod / dateModified).
 */
trait HasSeo
{
    abstract public function publicPath(?string $slug = null): string;

    protected function seoContentFields(): array
    {
        return ['name', 'desc'];
    }

    public static function bootHasSeo(): void
    {
        static::creating(function (self $model) {
            $model->slug = $model->uniqueSlug($model->slug ?: $model->name);
            $model->content_updated_at ??= now();
            // Models with a workflow (articles) get their date when they actually go live.
            if (in_array('published_at', $model->getFillable(), true) && in_array($model->getAttribute('status') ?? 'published', ['published'], true)) {
                $model->published_at ??= now();
            }
        });

        static::updating(function (self $model) {
            if ($model->isDirty('slug')) {
                $model->slug = $model->uniqueSlug($model->slug ?: $model->name);
                $old = $model->getOriginal('slug');
                if ($old && $old !== $model->slug) {
                    Redirect::remember($model->publicPath($old), $model->publicPath($model->slug));
                }
            }
            if ($model->isDirty($model->seoContentFields())) {
                $model->content_updated_at = now();
            }
        });

        static::deleted(function (self $model) {
            if ($model->slug) {
                Redirect::rememberGone($model->publicPath($model->slug));
            }
        });
    }

    public function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'page';
        $slug = $base;
        $i = 2;
        while (static::query()
            ->where('slug', $slug)
            ->when($this->exists, fn ($q) => $q->whereKeyNot($this->getKey()))
            ->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function getPublicUrlAttribute(): string
    {
        return url($this->publicPath());
    }
}
