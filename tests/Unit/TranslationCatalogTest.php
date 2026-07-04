<?php

function flattenTranslationCatalog(array $values, string $prefix = ''): array
{
    $result = [];

    foreach ($values as $key => $value) {
        $path = $prefix === '' ? $key : $prefix.'.'.$key;
        $result += is_array($value) ? flattenTranslationCatalog($value, $path) : [$path => $value];
    }

    return $result;
}

function translationPlaceholders(string $value): array
{
    preg_match_all('/:([a-z_]+)/i', $value, $matches);
    $placeholders = array_values(array_unique($matches[1]));
    sort($placeholders);

    return $placeholders;
}

test('all supported locales implement the complete translation contract', function () {
    $catalogs = [];

    foreach (['en', 'pl', 'de', 'es', 'fr'] as $locale) {
        $catalogs[$locale] = flattenTranslationCatalog(
            require __DIR__.'/../../resources/lang/'.$locale.'/messages.php'
        );
    }

    $referenceKeys = array_keys($catalogs['en']);
    expect($referenceKeys)->toHaveCount(227);

    foreach ($catalogs as $locale => $catalog) {
        expect(array_keys($catalog))->toBe($referenceKeys, "Translation keys differ for {$locale}");

        foreach ($catalog as $key => $value) {
            expect($value)->toBeString()->not->toBe('', "Empty translation: {$locale}.{$key}")
                ->and(translationPlaceholders($value))
                ->toBe(translationPlaceholders($catalogs['en'][$key]), "Placeholder mismatch: {$locale}.{$key}");
        }
    }
});
