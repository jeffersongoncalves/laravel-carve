# Laravel Carve

[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-support-FFDD00?style=flat-square&logo=buy-me-a-coffee&logoColor=black)](https://buymeacoffee.com/jeffersongoncalves)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jeffersongoncalves/laravel-carve.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-carve)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-carve/run-tests.yml?branch=master&label=tests&style=flat-square)](https://github.com/jeffersongoncalves/laravel-carve/actions?query=workflow%3Arun-tests+branch%3Amaster)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-carve/fix-php-code-style-issues.yml?branch=master&label=code%20style&style=flat-square)](https://github.com/jeffersongoncalves/laravel-carve/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/jeffersongoncalves/laravel-carve.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-carve)

[Carve](https://markup-carve.github.io/carve/) markup for Laravel, built on [markup-carve/carve-php](https://github.com/markup-carve/carve-php).

- **Named render profiles**: one for user comments, one for trusted docs, each with its own safe mode, feature preset and extensions.
- **Rendered results** with the HTML, a table of contents and parsed frontmatter.
- **Blade**: `@carve`, `@carveText` and an `<x-carve>` component.
- **`.crv` views**: `view('docs.intro')` renders `resources/views/docs/intro.crv`, with `{{ include.crv }}` support.
- **Eloquent cast** that stores source and reads back rendered HTML.
- **Validation rule** for presets, parse warnings, lint findings and length.
- **Str macros**: `Str::carve()`, `str($body)->carveText()->limit(160)`.
- **Import** from Markdown and HTML, and **lint** for Markdown habits.
- **Artisan**: `carve:render`, `carve:convert`, `carve:lint`.
- **Cache** keyed by profile configuration, so profiles never share HTML.

## Requirements

- PHP 8.2+
- Laravel 11, 12 or 13

## Installation

```bash
composer require jeffersongoncalves/laravel-carve
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag="carve-config"
```

To read YAML frontmatter with `RenderedCarve::meta()`, also install `symfony/yaml`. JSON frontmatter works without it.

## Quick start

```php
use JeffersonGoncalves\Carve\Facades\Carve;

Carve::toHtml('Some *bold* and /italic/ text');
// <p>Some <strong>bold</strong> and <em>italic</em> text</p>

Carve::toHtml($comment->body, 'comment'); // render with a named profile
Carve::toText($post->body);               // plain text, for excerpts and meta tags
Carve::toMarkdown($post->body);
Carve::toAnsi($post->body);               // for the terminal
Carve::parse($post->body);                // carve-php AST Document
```

## Profiles

Every entry of `carve.profiles` becomes its own converter. The published config ships three:

```php
'profiles' => [
    'default' => [
        'safe_mode' => true,
        'preset' => null,
        'extensions' => ['autolink'],
    ],

    // User generated content: comments, chat, reviews.
    'comment' => [
        'safe_mode' => 'strict',
        'preset' => 'comment',
        'extensions' => ['autolink', ['type' => 'external_links', 'nofollow' => true]],
    ],

    // Trusted content written by your team: docs, pages, posts.
    'trusted' => [
        'safe_mode' => false,
        'extensions' => ['autolink', 'admonition', 'details', 'heading_permalinks', 'tabs'],
    ],
],
```

| Option | Values |
|---|---|
| `safe_mode` | `true` escapes raw HTML and blocks dangerous URLs. `'strict'` also strips raw HTML and `style` attributes. `false` only for trusted content. |
| `preset` | Restricts the allowed markup: `null`, `full`, `article` (no raw HTML), `comment`, `minimal`. |
| `on_disallowed` | What a preset does with disallowed markup: `to_text` (default), `strip`, `error` (throws `ProfileViolationException`). |
| `mode` | `interactive` or `static` (print, e-mail, PDF). |
| `soft_break_mode` | `null`, `newline`, `space` or `br`. |
| `smart_typography` | `false` keeps `--` and straight quotes as written. |
| `xhtml` | Self-closing void tags. |
| `source_lines` | Adds `data-source-line` attributes for editor scroll-sync. |
| `symbols` | Trusted, unescaped HTML for `:name:` shortcodes. |
| `labels` | Overrides the strings the engine writes itself. |
| `extensions` | See below. |

`carve.default` (or `CARVE_PROFILE`) picks the profile used when none is given.

### Extensions

Enable an extension by name, or pass an array with its options in snake_case. Options map to the carve-php extension's constructor arguments, so every option carve-php supports is available:

```php
'extensions' => [
    'autolink',
    'spoiler',
    'mermaid',
    ['type' => 'table_of_contents', 'position' => 'top', 'min_level' => 2],
    ['type' => 'heading_permalinks', 'symbol' => '#', 'position' => 'after'],
    ['type' => 'external_links', 'target' => '_blank', 'internal_hosts' => ['example.com']],
    ['type' => 'wikilinks', 'url_template' => '/wiki/{page}'],
    ['type' => 'fenced_render', 'language' => 'chart'],
    App\Carve\MyExtension::class,
],
```

Available names: `admonition`, `ascii_heading_ids`, `autolink`, `citations`, `code_callouts`, `code_group`, `color_swatch`, `default_attributes`, `details`, `external_links`, `fenced_render`, `frontmatter`, `glossary`, `heading_level_shift`, `heading_numbers`, `heading_permalinks`, `heading_reference`, `img_fence`, `index`, `inline_footnotes`, `list_table`, `lowercase_heading_ids`, `math_block`, `mentions`, `plus_bullet`, `semantic_span`, `smart_quotes`, `spoiler`, `tab_normalize`, `table_of_contents`, `tabs`, `toc_placement`, `wikilinks`, plus the `mermaid` and `plantuml` shorthands for `fenced_render`.

An unknown name or option throws an `InvalidArgumentException` naming the extension.

### Customizing a profile in code

Anything config cannot hold, such as closures and render listeners, goes through `extend()`, usually in a service provider:

```php
use MarkupCarve\Carve\CarveConverter;

Carve::extend('default', function (CarveConverter $converter) {
    $converter->on('render.link', function ($event) {
        $event->getNode()->setAttribute('data-turbo', 'false');
    });
});

Carve::profile('default')->carve(); // the underlying carve-php converter
```

## Rendered results

`Carve::render()` returns a `RenderedCarve` holding the HTML, the headings and the frontmatter collected while rendering:

```php
$page = Carve::render(<<<'CARVE'
---yaml
title: Getting started
tags: [intro, setup]
---

# Getting started

## Install
CARVE);

$page->html;              // the frontmatter block is not part of the output
$page->toc();             // [['level' => 1, 'text' => 'Getting started', 'html' => '...', 'id' => 'Getting-started'], ...]
$page->meta();            // ['title' => 'Getting started', 'tags' => ['intro', 'setup']]
$page->meta('tags.0');    // 'intro'
$page->source;            // the original markup
```

It is `Htmlable`, so `{{ $page }}` prints the HTML unescaped, and `Arrayable`/`JsonSerializable`.

## Blade

```blade
@carve($post->body)
@carve($comment->body, 'comment')
@carveText($post->body) {{-- plain text, escaped --}}

<x-carve :source="$post->body" />
<x-carve :source="$comment->body" profile="comment" />

<x-carve>
    # Welcome back, {{ $user->name }}

    You have *{{ $count }}* new messages.
</x-carve>
```

The component slot is dedented, so it can follow your template's indentation. Values echoed with `{{ }}` inside the slot are Carve source: Carve markup in them is rendered, while HTML is always shown as text. Render user supplied values with a `safe_mode` profile.

## Views

Files ending in `.crv` inside your view paths render as Carve:

```php
return view('docs.intro'); // resources/views/docs/intro.crv
```

Set `carve.views.profile` to render them with a specific profile, `carve.views.extension` to change the extension, or `carve.views.enabled` to `false` to skip the view engine. View data is not interpolated; use the component for templates with variables.

### Includes

Set `carve.include_root` to an absolute directory to expand `{{ chapter.crv }}` directives in views, `Carve::renderFile()` and `carve:render`. Includes can only read files inside that directory. With `include_root` null, directives stay literal text.

```php
'include_root' => resource_path('docs'),
```

```php
Carve::renderFile(resource_path('docs/book.crv'))->html;
```

## Eloquent cast

```php
use JeffersonGoncalves\Carve\Casts\AsCarve;

class Post extends Model
{
    protected $casts = [
        'body' => AsCarve::class,
        'excerpt' => AsCarve::class.':comment', // with a profile
    ];
}
```

```php
$post->body = '# Hello *world*';  // stores the source
$post->body->html;                // rendered HTML
$post->body->source;              // stored markup
$post->body->toc();
{{ $post->body }}                 // prints the HTML in Blade
```

## Validation

```php
use JeffersonGoncalves\Carve\Rules\ValidCarve;

$request->validate([
    'body' => ['required', new ValidCarve],
    'comment' => ['required', ValidCarve::preset('comment')->maxLength(5000)],
    'docs' => ['required', (new ValidCarve)->strict()->lint()],
]);
```

- `preset('comment')` fails when the text uses markup the preset does not allow, instead of silently degrading it.
- `strict()` fails on parse warnings such as undefined references.
- `lint()` fails on lint findings such as Markdown's `**bold**`.
- `maxLength(n)` limits the length in characters.

Messages ship in English and Brazilian Portuguese. Publish them with `php artisan vendor:publish --tag="carve-translations"`.

## Str macros

```php
use Illuminate\Support\Str;

Str::carve('*bold*');                             // HTML
Str::carve($comment, 'comment');
Str::carveText($post->body);                      // plain text
str($post->body)->carveText()->squish()->limit(160); // meta description
```

## Import and lint

```php
Carve::fromMarkdown(file_get_contents('README.md')); // CommonMark/GFM to Carve
Carve::fromHtml($html);                              // HTML to Carve (not a sanitizer)

foreach (Carve::lint('**bold**') as $warning) {
    echo "{$warning->line}:{$warning->column} {$warning->rule} {$warning->message}";
}
```

## Artisan

```bash
php artisan carve:render docs/intro.crv                       # HTML to the console
php artisan carve:render docs/intro.crv --format=text         # text, markdown or ansi
php artisan carve:render docs/intro.crv --profile=trusted --output=public/intro.html

php artisan carve:convert README.md --output=README.crv       # from .md / .html (or --from=)

php artisan carve:lint resources/docs                         # exits 1 on findings
```

## Cache

```php
'cache' => [
    'enabled' => env('CARVE_CACHE', false),
    'store' => env('CARVE_CACHE_STORE'), // null = default store
    'ttl' => null,                       // seconds, null = until evicted
    'prefix' => 'carve',
],
```

Entries are keyed by the profile's configuration, the carve-php version and a hash of the source, so changing a profile or upgrading carve-php never serves stale HTML. `extend()` customizers are not part of the key: change `prefix` when one changes the output of a cached profile. File renders with includes are not cached, since an included file can change on its own.

## Security

- The `default` profile escapes raw HTML and blocks `javascript:`, `data:` and similar URLs. Use `'strict'` or a `comment`/`minimal` preset for user generated content.
- Output of `@carve`, `<x-carve>` and the cast is echoed unescaped because it is HTML. Only use a profile with `safe_mode => false` for content you trust.
- `symbols` values are inserted as raw HTML. Never fill them from user input.
- `fromHtml()` is not a sanitizer. Render its result with a safe profile when the HTML is untrusted.

## Testing

```bash
composer test
composer analyse
composer format
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Jefferson Gonçalves](https://github.com/jeffersongoncalves)
- [markup-carve/carve-php](https://github.com/markup-carve/carve-php) for the Carve engine
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
