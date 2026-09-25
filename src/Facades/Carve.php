<?php

namespace JeffersonGoncalves\Carve\Facades;

use Illuminate\Support\Facades\Facade;
use JeffersonGoncalves\Carve\CarveManager;

/**
 * @method static \JeffersonGoncalves\Carve\Converter profile(?string $name = null)
 * @method static string getDefaultProfile()
 * @method static list<string> profiles()
 * @method static CarveManager extend(string $profile, \Closure $callback)
 * @method static void flush()
 * @method static string toHtml(string $source, ?string $profile = null)
 * @method static \JeffersonGoncalves\Carve\RenderedCarve render(string $source, ?string $profile = null)
 * @method static \JeffersonGoncalves\Carve\RenderedCarve renderFile(string $path, ?string $profile = null)
 * @method static string toText(string $source, ?string $profile = null)
 * @method static string toMarkdown(string $source, ?string $profile = null)
 * @method static string toAnsi(string $source, ?string $profile = null)
 * @method static \MarkupCarve\Carve\Node\Document parse(string $source, ?string $profile = null)
 * @method static string fromMarkdown(string $markdown)
 * @method static string fromHtml(string $html)
 * @method static list<\MarkupCarve\Carve\Lint\LintWarning> lint(string $source)
 *
 * @see CarveManager
 */
class Carve extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CarveManager::class;
    }
}
