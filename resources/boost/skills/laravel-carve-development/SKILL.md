---
name: laravel-carve-development
description: Build and work with laravel-carve, including render profiles, the Carve facade, Blade directives and component, .crv views, the AsCarve cast, the ValidCarve rule, Str macros, Markdown/HTML import, linting and carve:* Artisan commands.
---

# Laravel Carve Development

## When to use this skill

Use this skill when:
- Rendering Carve markup to HTML, text, Markdown or ANSI
- Configuring or customizing render profiles in `config/carve.php`
- Storing Carve source on a model or validating user input
- Writing `.crv` views or converting Markdown/HTML to Carve

## Rendering

```php
use JeffersonGoncalves\Carve\Facades\Carve;

Carve::toHtml($source, 'comment');
Carve::toText($source);
Carve::toMarkdown($source);
Carve::toAnsi($source);
Carve::parse($source); // carve-php AST Document

$page = Carve::render($source); // RenderedCarve (Htmlable, Arrayable, JsonSerializable)
$page->html;
$page->toc();          // [['level' => 1, 'text' => '...', 'html' => '...', 'id' => '...'], ...]
$page->meta('tags.0'); // frontmatter; YAML needs symfony/yaml, JSON works without it

Carve::renderFile(resource_path('docs/book.crv')); // expands {{ file.crv }} inside carve.include_root
```

## Profiles

```php
'profiles' => [
    'comment' => [
        'safe_mode' => 'strict',        // true | 'strict' | false
        'preset' => 'comment',          // null | full | article | comment | minimal
        'on_disallowed' => 'to_text',   // to_text | strip | error (throws ProfileViolationException)
        'extensions' => [
            'autolink',
            ['type' => 'external_links', 'nofollow' => true],
            App\Carve\MyExtension::class,
        ],
    ],
],
```

Extension options are snake_case and map to carve-php constructor arguments. Closures and render listeners go through `extend()`:

```php
use MarkupCarve\Carve\CarveConverter;

Carve::extend('default', function (CarveConverter $converter) {
    $converter->on('render.link', fn ($event) => $event->getNode()->setAttribute('data-turbo', 'false'));
});
```

## Model and validation

```php
use JeffersonGoncalves\Carve\Casts\AsCarve;
use JeffersonGoncalves\Carve\Rules\ValidCarve;

protected $casts = ['body' => AsCarve::class];

$post->body = '# Hello *world*'; // stores source
$post->body->html;               // rendered HTML
$post->body->source;

ValidCarve::preset('comment')->strict()->lint()->maxLength(5000);
```

## Blade and views

```blade
@carve($post->body)
@carveText($post->body)
<x-carve :source="$comment->body" profile="comment" />
<x-carve>
    # Hi {{ $user->name }}
</x-carve>
```

The slot is dedented. `.crv` files render via `view()`; configure `carve.views.profile`, `extension`, `enabled`.

## Artisan

```bash
php artisan carve:render docs/intro.crv --format=text --profile=trusted --output=public/intro.html
php artisan carve:convert README.md --output=README.crv
php artisan carve:lint resources/docs   # exits 1 on findings
```

## Cache

`carve.cache.enabled` caches by profile config + carve-php version + source hash. `extend()` customizers are not part of the key: change `carve.cache.prefix` when one changes output.

## Troubleshooting

### `InvalidArgumentException` naming an extension

**Cause**: Unknown extension name or option in a profile.

**Solution**: Check the name against the README list and use snake_case option names.

### Raw HTML shows up in rendered output

**Cause**: The profile has `safe_mode => false`.

**Solution**: Use `default` or `comment` for anything users can write.

### `{{ file.crv }}` stays literal

**Cause**: `carve.include_root` is null, or the file is outside it.

**Solution**: Set `include_root` to an absolute directory containing the includes.
