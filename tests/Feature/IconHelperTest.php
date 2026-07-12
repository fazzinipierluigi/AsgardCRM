<?php

test('returns the outline variant by default', function () {
    $svg = icon('search');

    expect($svg)->toContain('<svg')
        ->and($svg)->toBe(file_get_contents(config('icons.path').'/outline/search.svg'));
});

test('an explicit variant is honored', function () {
    $svg = icon('search', 'filled');

    expect($svg)->toContain('<svg')
        ->and($svg)->toBe(file_get_contents(config('icons.path').'/filled/search.svg'));
});

test('an unknown icon returns an empty string instead of throwing', function () {
    expect(icon('not-a-real-icon-name'))->toBe('');
});

test('path traversal in the name is neutralized via basename', function () {
    expect(icon('../../../../etc/passwd'))->toBe('');
});

test('path traversal in the variant is neutralized via basename', function () {
    expect(icon('search', '../../../../etc'))->toBe('');
});
