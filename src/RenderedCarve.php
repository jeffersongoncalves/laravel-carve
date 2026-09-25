<?php

namespace JeffersonGoncalves\Carve;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use JsonSerializable;
use Stringable;
use Symfony\Component\Yaml\Yaml;

/**
 * The result of rendering a Carve document: the HTML plus the table of
 * contents and frontmatter collected while rendering it.
 *
 * Htmlable, so `{{ $rendered }}` in Blade prints the HTML unescaped.
 *
 * @implements Arrayable<string, mixed>
 */
final class RenderedCarve implements Arrayable, Htmlable, JsonSerializable, Stringable
{
    /**
     * @param  list<array{level: int, text: string, html: string, id: string}>  $toc
     * @param  array{format: string, content: string}|null  $frontmatter
     */
    public function __construct(
        public readonly string $source,
        public readonly string $html,
        public readonly array $toc = [],
        public readonly ?array $frontmatter = null,
    ) {}

    /**
     * @param  array{source: string, html: string, toc?: list<array{level: int, text: string, html: string, id: string}>, frontmatter?: array{format: string, content: string}|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['source'], $data['html'], $data['toc'] ?? [], $data['frontmatter'] ?? null);
    }

    public function toHtml(): string
    {
        return $this->html;
    }

    /**
     * Headings as a flat list: level, plain text, inline HTML and anchor id.
     *
     * @return list<array{level: int, text: string, html: string, id: string}>
     */
    public function toc(): array
    {
        return $this->toc;
    }

    public function hasFrontmatter(): bool
    {
        return $this->frontmatter !== null;
    }

    /**
     * Frontmatter parsed into an array. JSON is always supported; YAML needs
     * symfony/yaml. Unsupported formats and invalid content give [].
     *
     * @return array<mixed>
     */
    public function meta(?string $key = null, mixed $default = null): mixed
    {
        $meta = $this->parseFrontmatter();

        return $key === null ? $meta : data_get($meta, $key, $default);
    }

    /**
     * @return array{source: string, html: string, toc: list<array{level: int, text: string, html: string, id: string}>, frontmatter: array{format: string, content: string}|null}
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'html' => $this->html,
            'toc' => $this->toc,
            'frontmatter' => $this->frontmatter,
        ];
    }

    /**
     * @return array{source: string, html: string, toc: list<array{level: int, text: string, html: string, id: string}>, frontmatter: array{format: string, content: string}|null}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->html;
    }

    /**
     * @return array<mixed>
     */
    private function parseFrontmatter(): array
    {
        if ($this->frontmatter === null) {
            return [];
        }

        ['format' => $format, 'content' => $content] = $this->frontmatter;

        try {
            $parsed = match ($format) {
                'json' => json_decode($content, true, flags: JSON_THROW_ON_ERROR),
                'yaml', 'yml' => class_exists(Yaml::class) ? Yaml::parse($content) : null,
                default => null,
            };
        } catch (\Throwable) {
            return [];
        }

        return is_array($parsed) ? $parsed : [];
    }
}
