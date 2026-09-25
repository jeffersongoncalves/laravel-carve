<?php

namespace JeffersonGoncalves\Carve;

use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use InvalidArgumentException;
use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Converter\HtmlToCarve;
use MarkupCarve\Carve\Converter\MarkdownToCarve;
use MarkupCarve\Carve\Lint;
use MarkupCarve\Carve\Lint\LintWarning;
use MarkupCarve\Carve\Node\Document;

/**
 * Entry point behind the Carve facade. Holds one lazily built Converter per
 * configured profile and proxies unknown calls to the default one.
 *
 * @mixin Converter
 */
class CarveManager
{
    /** @var array<string, Converter> */
    private array $converters = [];

    /** @var array<string, list<Closure(CarveConverter): void>> */
    private array $customizers = [];

    public function __construct(
        private readonly Config $config,
        private readonly CacheFactory $cache,
        private readonly ConverterFactory $factory,
    ) {}

    public function profile(?string $name = null): Converter
    {
        $name ??= $this->getDefaultProfile();

        return $this->converters[$name] ??= $this->build($name);
    }

    public function getDefaultProfile(): string
    {
        return (string) $this->config->get('carve.default', 'default');
    }

    /**
     * @return list<string>
     */
    public function profiles(): array
    {
        return array_keys((array) $this->config->get('carve.profiles', []));
    }

    /**
     * Customize a profile's carve-php converter in code: register listeners,
     * closures or extensions that cannot live in config. Runs when the
     * profile is first built; an already built profile is rebuilt.
     *
     * Cache keys do not see customizers. Change `carve.cache.prefix` when a
     * customizer changes output of an already cached profile.
     *
     * @param  Closure(CarveConverter): void  $callback
     */
    public function extend(string $profile, Closure $callback): static
    {
        $this->customizers[$profile][] = $callback;
        unset($this->converters[$profile]);

        return $this;
    }

    /**
     * Forget built converters so the next call reads config again.
     */
    public function flush(): void
    {
        $this->converters = [];
    }

    public function toHtml(string $source, ?string $profile = null): string
    {
        return $this->profile($profile)->toHtml($source);
    }

    public function render(string $source, ?string $profile = null): RenderedCarve
    {
        return $this->profile($profile)->render($source);
    }

    public function renderFile(string $path, ?string $profile = null): RenderedCarve
    {
        return $this->profile($profile)->renderFile($path);
    }

    public function toText(string $source, ?string $profile = null): string
    {
        return $this->profile($profile)->toText($source);
    }

    public function toMarkdown(string $source, ?string $profile = null): string
    {
        return $this->profile($profile)->toMarkdown($source);
    }

    public function toAnsi(string $source, ?string $profile = null): string
    {
        return $this->profile($profile)->toAnsi($source);
    }

    public function parse(string $source, ?string $profile = null): Document
    {
        return $this->profile($profile)->parse($source);
    }

    /**
     * Convert CommonMark/GFM Markdown into Carve source.
     */
    public function fromMarkdown(string $markdown): string
    {
        return (new MarkdownToCarve)->convert($markdown);
    }

    /**
     * Convert HTML into Carve source. Not a sanitizer: render the result
     * with a safe profile when the HTML is untrusted.
     */
    public function fromHtml(string $html): string
    {
        return (new HtmlToCarve)->convert($html);
    }

    /**
     * Report constructs that parse but almost certainly do not mean what the
     * author intended (Markdown habits, retired spellings, table columns...).
     *
     * @return list<LintWarning>
     */
    public function lint(string $source): array
    {
        $warnings = array_merge(
            (new Lint\MarkdownHabitLinter)->lint($source),
            (new Lint\SemanticAttributeLinter)->lint($source),
            (new Lint\RetiredSpellingLinter)->lint($source),
            (new Lint\TableColumnLinter)->lint($source),
            (new Lint\TemplateSourceLinter)->lint($source),
            (new Lint\FigureGroupLinter)->lint($source),
            (new Lint\QuoteFenceLinter)->lint($source),
        );

        usort($warnings, fn (LintWarning $a, LintWarning $b): int => [$a->line, $a->column] <=> [$b->line, $b->column]);

        return $warnings;
    }

    /**
     * @param  array<mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->profile()->{$method}(...$arguments);
    }

    private function build(string $name): Converter
    {
        $config = $this->config->get("carve.profiles.{$name}");

        if (! is_array($config)) {
            throw new InvalidArgumentException(sprintf(
                'Carve profile "%s" is not defined. Available profiles: %s.',
                $name,
                implode(', ', $this->profiles()),
            ));
        }

        $carve = $this->factory->make($config);
        foreach ($this->customizers[$name] ?? [] as $customizer) {
            $customizer($carve);
        }

        $cache = (array) $this->config->get('carve.cache', []);
        $includeRoot = $this->config->get('carve.include_root');

        return new Converter(
            carve: $carve,
            signature: hash('xxh3', (string) json_encode([$name, $config, CarveConverter::LIB_VERSION])),
            cache: ($cache['enabled'] ?? false) ? $this->cache->store($cache['store'] ?? null) : null,
            ttl: isset($cache['ttl']) ? (int) $cache['ttl'] : null,
            prefix: (string) ($cache['prefix'] ?? 'carve'),
            includeRoot: is_string($includeRoot) && $includeRoot !== '' ? $includeRoot : null,
        );
    }
}
