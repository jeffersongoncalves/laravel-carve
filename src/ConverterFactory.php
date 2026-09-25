<?php

namespace JeffersonGoncalves\Carve;

use Illuminate\Support\Str;
use InvalidArgumentException;
use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Extension;
use MarkupCarve\Carve\Extension\ExtensionInterface;
use MarkupCarve\Carve\Extension\TableOfContentsExtension;
use MarkupCarve\Carve\Profile;
use MarkupCarve\Carve\Renderer\SoftBreakMode;
use MarkupCarve\Carve\SafeMode;
use Throwable;

/**
 * Builds a configured carve-php converter from one entry of `carve.profiles`.
 */
class ConverterFactory
{
    /**
     * Extension names accepted in a profile's `extensions` list.
     *
     * @var array<string, class-string<ExtensionInterface>>
     */
    public const EXTENSIONS = [
        'admonition' => Extension\AdmonitionExtension::class,
        'ascii_heading_ids' => Extension\AsciiHeadingIdsExtension::class,
        'autolink' => Extension\AutolinkExtension::class,
        'citations' => Extension\CitationsExtension::class,
        'code_callouts' => Extension\CodeCalloutsExtension::class,
        'code_group' => Extension\CodeGroupExtension::class,
        'color_swatch' => Extension\ColorSwatchExtension::class,
        'default_attributes' => Extension\DefaultAttributesExtension::class,
        'details' => Extension\DetailsExtension::class,
        'external_links' => Extension\ExternalLinksExtension::class,
        'fenced_render' => Extension\FencedRenderExtension::class,
        'frontmatter' => Extension\FrontmatterExtension::class,
        'glossary' => Extension\GlossaryExtension::class,
        'heading_level_shift' => Extension\HeadingLevelShiftExtension::class,
        'heading_numbers' => Extension\HeadingNumbersExtension::class,
        'heading_permalinks' => Extension\HeadingPermalinksExtension::class,
        'heading_reference' => Extension\HeadingReferenceExtension::class,
        'img_fence' => Extension\ImgFenceExtension::class,
        'index' => Extension\IndexExtension::class,
        'inline_footnotes' => Extension\InlineFootnotesExtension::class,
        'list_table' => Extension\ListTableExtension::class,
        'lowercase_heading_ids' => Extension\LowercaseHeadingIdsExtension::class,
        'math_block' => Extension\MathBlockExtension::class,
        'mentions' => Extension\MentionsExtension::class,
        'plus_bullet' => Extension\PlusBulletExtension::class,
        'semantic_span' => Extension\SemanticSpanExtension::class,
        'smart_quotes' => Extension\SmartQuotesExtension::class,
        'spoiler' => Extension\SpoilerExtension::class,
        'tab_normalize' => Extension\TabNormalizeExtension::class,
        'table_of_contents' => TableOfContentsExtension::class,
        'tabs' => Extension\TabsExtension::class,
        'toc_placement' => Extension\TocPlacementExtension::class,
        'wikilinks' => Extension\WikilinksExtension::class,
    ];

    /**
     * Shorthands that preconfigure another extension.
     *
     * @var array<string, array{type: string, language: string|list<string>}>
     */
    public const ALIASES = [
        'mermaid' => ['type' => 'fenced_render', 'language' => 'mermaid'],
        'plantuml' => ['type' => 'fenced_render', 'language' => ['plantuml', 'puml']],
    ];

    public const PRESETS = ['full', 'article', 'comment', 'minimal'];

    /**
     * @param  array<string, mixed>  $config
     */
    public function make(array $config): CarveConverter
    {
        $converter = new CarveConverter(
            xhtml: (bool) ($config['xhtml'] ?? false),
            safeMode: $this->safeMode($config['safe_mode'] ?? true),
            profile: $this->preset($config['preset'] ?? null, $config['on_disallowed'] ?? null),
            softBreakMode: isset($config['soft_break_mode']) ? SoftBreakMode::from($config['soft_break_mode']) : null,
            smartTypography: isset($config['smart_typography']) ? (bool) $config['smart_typography'] : null,
            mode: $config['mode'] ?? 'interactive',
            symbols: $config['symbols'] ?? [],
            labels: $config['labels'] ?? [],
            sourceLines: (bool) ($config['source_lines'] ?? false),
        );

        foreach ($config['extensions'] ?? [] as $extension) {
            $converter->addExtension($this->extension($extension));
        }

        // Always collect headings so RenderedCarve::toc() works without the
        // user enabling the extension. With no position it adds no output.
        $hasToc = collect($converter->getExtensions())->contains(fn ($extension) => $extension instanceof TableOfContentsExtension);
        if (! $hasToc) {
            $converter->addExtension(new TableOfContentsExtension);
        }

        return $converter;
    }

    /**
     * @param  string|class-string<ExtensionInterface>|array<string, mixed>|ExtensionInterface  $config
     */
    public function extension(string|array|ExtensionInterface $config): ExtensionInterface
    {
        if ($config instanceof ExtensionInterface) {
            return $config;
        }

        if (is_string($config)) {
            $config = ['type' => $config];
        }

        $type = $config['type'] ?? throw new InvalidArgumentException('Carve extension config needs a "type" key.');
        unset($config['type']);

        if (isset(self::ALIASES[$type])) {
            $alias = self::ALIASES[$type];
            $type = $alias['type'];
            $config += ['language' => $alias['language']];
        }

        if ($type === 'wikilinks' && isset($config['url_template'])) {
            $config['url_generator'] = $this->wikilinkUrls($config['url_template']);
            unset($config['url_template']);
        }

        $class = self::EXTENSIONS[$type] ?? $type;

        if (! is_a($class, ExtensionInterface::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown Carve extension "%s". Use one of: %s, or an ExtensionInterface class.',
                $type,
                implode(', ', array_keys(self::EXTENSIONS + self::ALIASES)),
            ));
        }

        $arguments = [];
        foreach ($config as $key => $value) {
            $arguments[Str::camel($key)] = $value;
        }

        try {
            return new $class(...$arguments);
        } catch (Throwable $e) {
            throw new InvalidArgumentException(sprintf('Invalid options for Carve extension "%s": %s', $type, $e->getMessage()), 0, $e);
        }
    }

    public function safeMode(mixed $value): SafeMode|bool
    {
        return match ($value) {
            'strict' => SafeMode::strict(),
            true, 'true', 1, '1' => true,
            false, 'false', 0, '0', null => false,
            default => $value instanceof SafeMode
                ? $value
                : throw new InvalidArgumentException('carve safe_mode must be true, false or "strict".'),
        };
    }

    public function preset(?string $name, ?string $onDisallowed = null): ?Profile
    {
        if ($name === null) {
            return null;
        }

        if (! in_array($name, self::PRESETS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown Carve preset "%s". Use one of: %s.', $name, implode(', ', self::PRESETS)));
        }

        $profile = Profile::{$name}();

        return $onDisallowed === null ? $profile : $profile->onDisallowed($onDisallowed);
    }

    private function wikilinkUrls(string $template): \Closure
    {
        return static fn (string $page): string => str_replace('{page}', Str::slug($page), $template);
    }
}
