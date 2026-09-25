<?php

namespace JeffersonGoncalves\Carve;

use Illuminate\Contracts\Cache\Repository;
use InvalidArgumentException;
use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Extension\FrontmatterExtension;
use MarkupCarve\Carve\Extension\TableOfContentsExtension;
use MarkupCarve\Carve\Node\Document;
use MarkupCarve\Carve\Renderer\AnsiRenderer;
use MarkupCarve\Carve\Renderer\MarkdownRenderer;
use MarkupCarve\Carve\Renderer\PlainTextRenderer;
use MarkupCarve\Carve\Transform\FilesystemIncludeResolver;
use MarkupCarve\Carve\Transform\IncludeExpander;

/**
 * One configured render profile.
 */
class Converter
{
    public function __construct(
        private readonly CarveConverter $carve,
        private readonly string $signature,
        private readonly ?Repository $cache = null,
        private readonly ?int $ttl = null,
        private readonly string $prefix = 'carve',
        private readonly ?string $includeRoot = null,
    ) {}

    public function toHtml(string $source): string
    {
        return $this->render($source)->html;
    }

    public function render(string $source): RenderedCarve
    {
        return $this->remember($source, fn (): RenderedCarve => $this->renderDocument($source, $this->carve->parse($source)));
    }

    /**
     * Render a file. With `carve.include_root` set, `{{ file.crv }}`
     * directives are expanded from inside that root.
     */
    public function renderFile(string $path): RenderedCarve
    {
        $realPath = realpath($path);
        if ($realPath === false || ! is_file($realPath) || ($source = file_get_contents($realPath)) === false) {
            throw new InvalidArgumentException(sprintf('Carve file is not readable: %s', $path));
        }

        if ($this->includeRoot === null) {
            return $this->render($source);
        }

        // ponytail: no cache here - an included file can change without the
        // parent changing. Hash the dependency list if file renders get hot.
        $expander = new IncludeExpander(
            resolver: new FilesystemIncludeResolver($this->includeRoot),
            currentPath: $realPath,
            source: $source,
            extensions: $this->carve->getExtensions(),
        );

        $document = $this->carve->transform($this->carve->parse($source), $expander);

        return $this->renderDocument($source, $document);
    }

    public function toText(string $source): string
    {
        return rtrim((new PlainTextRenderer)->render($this->parse($source)), "\n");
    }

    public function toMarkdown(string $source): string
    {
        return (new MarkdownRenderer)->render($this->parse($source));
    }

    public function toAnsi(string $source): string
    {
        return (new AnsiRenderer)->render($this->parse($source));
    }

    public function parse(string $source): Document
    {
        return $this->carve->parse($source);
    }

    /**
     * The underlying carve-php converter, for listeners or extensions that
     * cannot be expressed in config.
     */
    public function carve(): CarveConverter
    {
        return $this->carve;
    }

    private function renderDocument(string $source, Document $document): RenderedCarve
    {
        $html = $this->carve->render($document);
        $toc = [];
        $frontmatter = null;

        foreach ($this->carve->getExtensions() as $extension) {
            if ($extension instanceof TableOfContentsExtension && $toc === []) {
                $toc = $extension->getToc();
            }
            if ($extension instanceof FrontmatterExtension && $extension->hasFrontmatter()) {
                $frontmatter = ['format' => (string) $extension->getFormat(), 'content' => (string) $extension->getContent()];
            }
        }

        return new RenderedCarve($source, $html, $toc, $frontmatter);
    }

    /**
     * @param  \Closure(): RenderedCarve  $render
     */
    private function remember(string $source, \Closure $render): RenderedCarve
    {
        if ($this->cache === null) {
            return $render();
        }

        $key = $this->prefix.':'.$this->signature.':'.hash('xxh3', $source);

        /** @var array{source: string, html: string, toc?: list<array{level: int, text: string, html: string, id: string}>, frontmatter?: array{format: string, content: string}|null}|null $cached */
        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            return RenderedCarve::fromArray($cached);
        }

        $rendered = $render();
        $this->cache->put($key, $rendered->toArray(), $this->ttl);

        return $rendered;
    }
}
