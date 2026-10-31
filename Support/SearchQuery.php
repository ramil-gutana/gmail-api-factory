<?php

namespace App\Shared\Gmail\Support;

/**
 * Turns a filter array into a Gmail search query (the "q" parameter).
 */
final class SearchQuery
{
    /**
     * Filters that are Gmail "has:<value>" flags rather than "<name>:<value>" pairs.
     */
    private const array HAS_FLAGS = [
        'has_attachment' => 'attachment',
        'has_userlabels' => 'userlabels',
        'has_nouserlabels' => 'nouserlabels',
    ];

    public static function build(array $filters): string
    {
        $terms = [];

        foreach (array_filter($filters, fn ($value) => ! empty($value)) as $name => $value) {
            $terms[] = isset(self::HAS_FLAGS[$name])
                ? 'has:'.self::HAS_FLAGS[$name]
                : self::term($name, (array) $value);
        }

        return implode(' ', $terms);
    }

    private static function term(string $name, array $values): string
    {
        $terms = array_map(fn ($value) => "$name:".self::quote((string) $value), $values);

        return count($terms) > 1 ? '('.implode(' OR ', $terms).')' : $terms[0];
    }

    private static function quote(string $value): string
    {
        if (! preg_match('/[\s()"]/', $value)) {
            return $value;
        }

        return '"'.str_replace('"', '', $value).'"';
    }
}
