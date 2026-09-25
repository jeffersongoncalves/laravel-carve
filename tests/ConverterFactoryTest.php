<?php

use JeffersonGoncalves\Carve\ConverterFactory;
use JeffersonGoncalves\Carve\Facades\Carve;
use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Extension\AutolinkExtension;
use MarkupCarve\Carve\Extension\ExtensionInterface;
use MarkupCarve\Carve\Extension\SpoilerExtension;
use MarkupCarve\Carve\Extension\TableOfContentsExtension;
use MarkupCarve\Carve\Profile;
use MarkupCarve\Carve\SafeMode;

it('builds every named extension and alias with default options', function (string $name) {
    expect((new ConverterFactory)->extension($name))->toBeInstanceOf(ExtensionInterface::class);
})->with(array_keys(array_diff_key(ConverterFactory::EXTENSIONS + ConverterFactory::ALIASES, ['fenced_render' => true])));

it('maps every named extension to an existing class', function () {
    foreach (ConverterFactory::EXTENSIONS as $class) {
        expect(is_a($class, ExtensionInterface::class, true))->toBeTrue();
    }
});

it('requires a language for fenced_render', function () {
    (new ConverterFactory)->extension('fenced_render');
})->throws(InvalidArgumentException::class, 'Invalid options for Carve extension "fenced_render"');

it('renders with every extension enabled at once', function () {
    // heading_reference and wikilinks both claim [[...]]; carve-php refuses the pair.
    $extensions = array_keys(array_diff_key(ConverterFactory::EXTENSIONS + ConverterFactory::ALIASES, ['heading_reference' => true]));
    $extensions[array_search('fenced_render', $extensions)] = ['type' => 'fenced_render', 'language' => 'chart'];
    config()->set('carve.profiles.default.extensions', $extensions);

    expect(Carve::toHtml("# Title\n\nSome *text* https://example.com"))->toContain('Title');
});

it('passes snake_case options as constructor arguments', function () {
    config()->set('carve.profiles.default.extensions', [
        ['type' => 'external_links', 'target' => '_blank', 'nofollow' => true, 'internal_hosts' => ['mine.test']],
    ]);

    $html = Carve::toHtml('[a](https://other.test) [b](https://mine.test)');

    expect($html)->toContain('<a href="https://other.test" target="_blank" rel="noopener noreferrer nofollow">a</a>')
        ->and($html)->toContain('<a href="https://mine.test">b</a>');
});

it('supports the mermaid and plantuml shorthands', function () {
    config()->set('carve.profiles.default.extensions', ['mermaid']);

    expect(Carve::toHtml("```mermaid\ngraph TD; A-->B\n```"))->toContain('class="mermaid"');
});

it('builds wikilink urls from a template', function () {
    config()->set('carve.profiles.default.extensions', [['type' => 'wikilinks', 'url_template' => '/wiki/{page}']]);

    expect(Carve::toHtml('See [[Getting Started]].'))->toContain('href="/wiki/getting-started"');
});

it('accepts extension class names and instances', function () {
    $factory = new ConverterFactory;

    expect($factory->extension(SpoilerExtension::class))->toBeInstanceOf(SpoilerExtension::class)
        ->and($factory->extension(['type' => AutolinkExtension::class, 'allowed_schemes' => ['https']]))->toBeInstanceOf(AutolinkExtension::class);

    $instance = new SpoilerExtension;
    expect($factory->extension($instance))->toBe($instance);
});

it('rejects unknown extensions', function () {
    (new ConverterFactory)->extension('nope');
})->throws(InvalidArgumentException::class, 'Unknown Carve extension "nope"');

it('rejects arrays without a type', function () {
    (new ConverterFactory)->extension(['target' => '_blank']);
})->throws(InvalidArgumentException::class, 'needs a "type" key');

it('names the extension when an option is wrong', function () {
    (new ConverterFactory)->extension(['type' => 'external_links', 'not_an_option' => true]);
})->throws(InvalidArgumentException::class, 'Invalid options for Carve extension "external_links"');

it('always registers one table of contents collector', function () {
    $converter = (new ConverterFactory)->make([]);
    $tocs = array_filter($converter->getExtensions(), fn ($e) => $e instanceof TableOfContentsExtension);

    expect($converter)->toBeInstanceOf(CarveConverter::class)
        ->and($tocs)->toHaveCount(1);

    $converter = (new ConverterFactory)->make(['extensions' => ['table_of_contents']]);
    expect(array_filter($converter->getExtensions(), fn ($e) => $e instanceof TableOfContentsExtension))->toHaveCount(1);
});

it('resolves safe mode values', function (mixed $value, mixed $expected) {
    $result = (new ConverterFactory)->safeMode($value);

    $expected === SafeMode::class
        ? expect($result)->toBeInstanceOf(SafeMode::class)
        : expect($result)->toBe($expected);
})->with([
    'true' => [true, true],
    'false' => [false, false],
    'null' => [null, false],
    'env string true' => ['true', true],
    'env string false' => ['false', false],
    'strict' => ['strict', SafeMode::class],
]);

it('rejects unknown safe mode values', function () {
    (new ConverterFactory)->safeMode('sometimes');
})->throws(InvalidArgumentException::class);

it('escapes raw html by default and strips it and style attributes in strict mode', function () {
    $source = "```=html\n<b>raw</b>\n```\n\n[s]{style=\"color:red\"}";

    expect(Carve::toHtml($source))->toContain('&lt;b&gt;raw&lt;/b&gt;')->toContain('style="color:red"');

    config()->set('carve.profiles.default.safe_mode', 'strict');
    Carve::flush();

    expect(Carve::toHtml($source))->not->toContain('raw')->not->toContain('style=');
});

it('resolves presets', function () {
    $factory = new ConverterFactory;

    expect($factory->preset(null))->toBeNull()
        ->and($factory->preset('comment')?->getName())->toBe('comment')
        ->and($factory->preset('article', Profile::ACTION_STRIP)?->getDisallowedAction())->toBe(Profile::ACTION_STRIP);

    $factory->preset('everything');
})->throws(InvalidArgumentException::class, 'Unknown Carve preset "everything"');

it('applies renderer options from config', function () {
    config()->set('carve.profiles.default', [
        'xhtml' => true,
        'soft_break_mode' => 'br',
        'source_lines' => true,
        'smart_typography' => false,
        'symbols' => ['heart' => '&hearts;'],
    ]);

    $html = Carve::toHtml("line one\nline two -- :heart:");

    expect($html)->toContain('<br />')
        ->toContain('data-source-line="1"')
        ->toContain('--')
        ->toContain('&hearts;');
});

it('renders static mode', function () {
    config()->set('carve.profiles.default.mode', 'static');

    expect(Carve::profile()->carve()->getRenderMode())->toBe('static');
});
