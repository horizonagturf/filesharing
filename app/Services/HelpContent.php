<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class HelpContent
{
    /**
     * @return array<int, string>
     */
    public function topics(): array
    {
        $topics = config('help.topics', []);

        uasort($topics, fn (array $a, array $b): int => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));

        return array_keys($topics);
    }

    public function exists(string $slug): bool
    {
        return array_key_exists($slug, config('help.topics', []));
    }

    public function title(string $slug): string
    {
        return (string) __("help.topics.{$slug}.title");
    }

    public function description(string $slug): string
    {
        return (string) __("help.topics.{$slug}.description");
    }

    public function body(string $slug): string
    {
        $path = $this->resolveMarkdownPath($slug);

        abort_unless($path !== null, 404);

        return Str::markdown(File::get($path));
    }

    private function resolveMarkdownPath(string $slug): ?string
    {
        $locale = app()->getLocale();
        $fallback = config('app.fallback_locale', 'en');
        $base = resource_path('docs/help');

        foreach (array_unique([$locale, $fallback]) as $candidate) {
            $path = "{$base}/{$candidate}/{$slug}.md";

            if (File::isFile($path)) {
                return $path;
            }
        }

        return null;
    }
}
