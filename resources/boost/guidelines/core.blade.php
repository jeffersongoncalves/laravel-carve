## Laravel Carve

Carve markup for Laravel, built on `markup-carve/carve-php`: named render profiles, Blade directives and component, `.crv` views, an Eloquent cast, Str macros, a validation rule, linting, Markdown/HTML import and Artisan commands. Requires PHP 8.2+ and Laravel 11, 12 or 13.

**Namespace:** `JeffersonGoncalves\Carve`
**Facade:** `JeffersonGoncalves\Carve\Facades\Carve`
**Config:** `config/carve.php` (`php artisan vendor:publish --tag="carve-config"`)

### Usage

@verbatim
<code-snippet name="Render Carve" lang="php">
use JeffersonGoncalves\Carve\Facades\Carve;

Carve::toHtml($post->body);              // default profile
Carve::toHtml($comment->body, 'comment'); // named profile
Carve::toText($post->body);              // plain text for excerpts / meta tags
$page = Carve::render($post->body);      // RenderedCarve: ->html, ->toc(), ->meta('title')
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Blade" lang="blade">
@carve($post->body)
@carve($comment->body, 'comment')
@carveText($post->body)
<x-carve :source="$comment->body" profile="comment" />
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Cast and validation" lang="php">
use JeffersonGoncalves\Carve\Casts\AsCarve;
use JeffersonGoncalves\Carve\Rules\ValidCarve;

protected $casts = ['body' => AsCarve::class.':comment'];

$request->validate([
    'body' => ['required', ValidCarve::preset('comment')->maxLength(5000)],
]);
</code-snippet>
@endverbatim

### Profiles

`carve.profiles` ships `default` (`safe_mode => true`), `comment` (`safe_mode => 'strict'`, `comment` preset) and `trusted` (`safe_mode => false`). `carve.default` / `CARVE_PROFILE` picks the default. Customize a converter in code with `Carve::extend('name', fn (CarveConverter $c) => ...)`.

### Other features

- `.crv` files in view paths render via `view('docs.intro')`; view data is not interpolated.
- `Str::carve()`, `Str::carveText()`, `str($body)->carveText()->limit(160)`.
- `Carve::fromMarkdown()`, `Carve::fromHtml()` (not a sanitizer), `Carve::lint()`.
- Artisan: `carve:render`, `carve:convert`, `carve:lint`.

### Best Practices

- Store Carve source in the database, never rendered HTML; `AsCarve` renders on read.
- `@carve`, `<x-carve>` and the cast output unescaped HTML. Use a safe profile (`default`, `comment`) for user content; `safe_mode => false` only for trusted content.
- Never fill `symbols` from user input: they are inserted as raw HTML.
- Carve is not Markdown: bold is `*bold*`, italic is `/italic/`. Run `Carve::lint()` or `ValidCarve::lint()` to catch Markdown habits.
