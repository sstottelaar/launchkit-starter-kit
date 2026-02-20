<?php

namespace App\Tags;

use Statamic\Tags\Tags;

class FaqSchema extends Tags
{
    /**
     * The {{ faq_schema }} tag.
     * Outputs JSON-LD structured data for FAQPage schema.
     */
    public function index(): string
    {
        $faqItems = $this->context->get('faq_items') ?? [];

        if (empty($faqItems)) {
            return '';
        }

        $mainEntity = collect($faqItems)->map(function ($item) {
            $question = $this->extractValue($item, 'question');
            $answer = $this->extractValue($item, 'answer');

            if (blank($question) || blank($answer)) {
                return null;
            }

            return [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => strip_tags($answer),
                ],
            ];
        })->filter()->values()->all();

        if (empty($mainEntity)) {
            return '';
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $mainEntity,
        ];

        return '<script type="application/ld+json">'.json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'</script>';
    }

    /**
     * Extract a value from an item (entry, array, or value object).
     */
    private function extractValue(mixed $item, string $key): string
    {
        if (is_array($item)) {
            return (string) ($item[$key] ?? '');
        }

        if (is_object($item) && method_exists($item, 'value')) {
            return (string) $item->value($key);
        }

        if (is_object($item) && method_exists($item, 'get')) {
            return (string) $item->get($key, '');
        }

        return '';
    }
}
