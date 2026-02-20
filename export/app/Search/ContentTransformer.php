<?php

namespace App\Search;

use Statamic\Contracts\Search\Searchable;
use Statamic\Facades\Entry;

class ContentTransformer
{
    /**
     * Keys to skip when recursively extracting - structural/metadata, not content.
     *
     * @var array<string>
     */
    protected array $skipKeys = [
        'type', 'id', '_id', '_key', 'handle', 'display', 'instructions',
        'media_position', 'columns', 'intro_alignment',
    ];

    /**
     * Transform the value for the search index. Extracts plain text from
     * building_blocks (Bard, replicators) and other content fields.
     */
    public function handle(mixed $value, string $field, Searchable $searchable): string
    {
        if (! method_exists($searchable, 'get')) {
            return '';
        }

        return $this->extractSearchableContent($searchable);
    }

    protected function extractSearchableContent($entry): string
    {
        $parts = [];

        $parts[] = $entry->get('title', '');
        $parts[] = $entry->get('teaser_text', '');
        $parts[] = $entry->get('lead', '');
        $parts[] = $entry->get('pretitle', '');

        $buildingBlocks = $entry->get('building_blocks', []);
        foreach ($buildingBlocks as $block) {
            if (is_array($block)) {
                $parts[] = $this->extractFromValue($block);
            }
        }

        return implode(' ', array_filter(array_map('trim', $parts)));
    }

    /**
     * Recursively extract plain text from any value (string, array, Bard, etc.).
     */
    protected function extractFromValue(mixed $value): string
    {
        if (is_string($value)) {
            return $this->isStatamicReference($value) ? '' : $value;
        }

        if (! is_array($value)) {
            return '';
        }

        if ($this->isBardContent($value)) {
            return $this->extractFromBard($value);
        }

        if ($this->isListOfStrings($value)) {
            return $this->extractFromEntryReferences($value);
        }

        $parts = [];
        foreach ($value as $key => $item) {
            if (in_array($key, $this->skipKeys, true)) {
                continue;
            }
            $parts[] = $this->extractFromValue($item);
        }

        return implode(' ', array_filter(array_map('trim', $parts)));
    }

    protected function isBardContent(array $value): bool
    {
        if (! isset($value[0]) || ! is_array($value[0])) {
            return false;
        }

        $first = $value[0];

        return array_key_exists('type', $first) && (array_key_exists('text', $first) || array_key_exists('content', $first));
    }

    protected function extractFromBard(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (! is_array($value)) {
            return '';
        }

        $texts = [];
        foreach ($value as $node) {
            if (isset($node['text'])) {
                $texts[] = $node['text'];
            }
            if (isset($node['content'])) {
                $texts[] = $this->extractFromBard($node['content']);
            }
        }

        return implode(' ', $texts);
    }

    protected function isListOfStrings(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_string($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extract text from an array of entry references. Handles both ID strings
     * (resolves via Entry::find) and already-resolved entry data (arrays).
     */
    protected function extractFromEntryReferences(array $items): string
    {
        $parts = [];

        foreach ($items as $item) {
            if (is_string($item)) {
                if ($this->looksLikeEntryId($item)) {
                    $entry = Entry::find($item);
                    if ($entry !== null) {
                        /** @var \Statamic\Entries\Entry $entry */
                        $parts[] = $this->extractFromValue($entry->data()->all());
                    }
                } elseif (! $this->isStatamicReference($item)) {
                    $parts[] = $item;
                }
            } elseif (is_array($item)) {
                $parts[] = $this->extractFromValue($item);
            }
        }

        return implode(' ', array_filter(array_map('trim', $parts)));
    }

    protected function looksLikeEntryId(string $value): bool
    {
        return strlen($value) >= 15
            && strlen($value) <= 60
            && preg_match('/^[a-zA-Z0-9_-]+$/', $value) === 1
            && ! str_contains($value, ' ');
    }

    /**
     * Check if a string is a Statamic reference (entry::*, term::*, asset::*, etc.)
     * that should not be indexed as searchable content.
     */
    protected function isStatamicReference(string $value): bool
    {
        return preg_match('/^(entry|term|asset|url)::[\w\-]+$/', $value) === 1;
    }
}
