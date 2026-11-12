<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Renders user-supplied markdown (e.g. bundle descriptions) with raw HTML
 * stripped and unsafe link schemes (javascript:, data:, ...) removed.
 *
 * Use this for anything a user typed. Str::markdown() on its own passes raw
 * HTML straight through, which is a stored XSS vector.
 */
class SafeMarkdown
{
    public static function render(?string $markdown): ?string
    {
        if ($markdown === null || trim($markdown) === '') {
            return null;
        }

        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
