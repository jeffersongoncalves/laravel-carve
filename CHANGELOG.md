# Changelog

All notable changes to `laravel-carve` will be documented in this file.

## v1.1.0 - 2026-09-25

### Added\n\n- Laravel Boost support: `resources/boost/guidelines/core.blade.php` and the `laravel-carve-development` skill. Apps using Laravel Boost pick them up on `php artisan boost:install` / `boost:update`.

## v1.0.0 - 2026-09-25

Initial release.

Carve markup for Laravel, built on markup-carve/carve-php.

- Named render profiles with safe mode (true / strict / false), feature presets (full, article, comment, minimal) and extensions from config
- All carve-php extensions by name, with snake_case options mapped to constructor arguments, plus mermaid and plantuml shorthands
- `Carve::render()` returns `RenderedCarve` with HTML, table of contents and parsed frontmatter (JSON, or YAML with symfony/yaml)
- `Carve::extend()` to customize a profile's converter in code
- Blade: `@carve`, `@carveText` and the `<x-carve>` component
- `.crv` view engine with sandboxed `{{ include.crv }}` support
- `AsCarve` Eloquent cast
- `ValidCarve` validation rule (preset, strict, lint, maxLength), messages in en and pt_BR
- `Str::carve()` / `Str::carveText()` and Stringable macros
- Markdown and HTML import, linting
- Artisan: `carve:render`, `carve:convert`, `carve:lint`
- Cache keyed by profile configuration and carve-php version
