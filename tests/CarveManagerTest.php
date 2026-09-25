<?php

use Illuminate\Support\Facades\Cache;
use JeffersonGoncalves\Carve\CarveManager;
use JeffersonGoncalves\Carve\Converter;
use JeffersonGoncalves\Carve\Facades\Carve;
use JeffersonGoncalves\Carve\RenderedCarve;
use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Exception\ProfileViolationException;
use MarkupCarve\Carve\Node\Document;

it('renders carve to html', function () {
    expect(Carve::toHtml('Some *bold* and /italic/ text'))
        ->toContain('<strong>bold</strong>')
        ->toContain('<em>italic</em>');
});

it('resolves the manager as a singleton under two names', function () {
    expect(app(CarveManager::class))->toBe(app('carve'));
});

it('escapes raw html and dangerous urls with safe mode on by default', function () {
    $html = Carve::toHtml("<script>alert(1)</script> [x](javascript:alert(1))\n\n```=html\n<b>raw</b>\n```");

    expect($html)
        ->not->toContain('<script>')
        ->not->toContain('javascript:')
        ->not->toContain('<b>raw</b>');
});

it('passes raw html through a profile with safe mode off', function () {
    expect(Carve::toHtml("```=html\n<b>raw</b>\n```", 'trusted'))->toContain('<b>raw</b>');
});

it('degrades markup a preset disallows', function () {
    $html = Carve::toHtml("# Heading\n\n![img](x.png)", 'comment');

    expect($html)->not->toContain('<h1')->not->toContain('<img');
});

it('throws on disallowed markup when the preset is set to error', function () {
    config()->set('carve.profiles.chat', ['preset' => 'minimal', 'on_disallowed' => 'error']);

    Carve::toHtml('# Heading', 'chat');
})->throws(ProfileViolationException::class);

it('uses the configured default profile', function () {
    config()->set('carve.default', 'trusted');

    expect(Carve::getDefaultProfile())->toBe('trusted')
        ->and(Carve::toHtml("```=html\n<i>x</i>\n```"))->toContain('<i>x</i>');
});

it('lists profiles and rejects unknown ones', function () {
    expect(Carve::profiles())->toBe(['default', 'comment', 'trusted']);

    Carve::profile('nope');
})->throws(InvalidArgumentException::class, 'Carve profile "nope" is not defined. Available profiles: default, comment, trusted.');

it('builds each profile once', function () {
    expect(Carve::profile())->toBeInstanceOf(Converter::class)
        ->and(Carve::profile())->toBe(Carve::profile('default'))
        ->and(Carve::profile('comment'))->not->toBe(Carve::profile('default'));
});

it('proxies unknown calls to the default profile', function () {
    expect(Carve::carve())->toBeInstanceOf(CarveConverter::class);
});

it('lets code customize a profile with extend', function () {
    Carve::profile(); // built before extend: must be rebuilt

    Carve::extend('default', function (CarveConverter $converter) {
        $converter->addOutputTransformer(fn (string $html): string => '<div class="prose">'.$html.'</div>');
    });

    expect(Carve::toHtml('hi'))->toStartWith('<div class="prose"><p>hi</p>');
});

it('renders text, markdown and ansi', function () {
    $source = "# Title\n\nSome *bold* text.";

    expect(Carve::toText($source))->toContain('Title')->toContain('Some bold text.')->not->toContain('*')
        ->and(Carve::toMarkdown($source))->toContain('# Title')->toContain('**bold**')
        ->and(Carve::toAnsi($source))->toContain("\e[");
});

it('applies the profile preset to plain text too', function () {
    expect(Carve::toText('![alt](x.png) hi', 'comment'))->not->toContain('x.png');
});

it('parses to an ast document', function () {
    expect(Carve::parse('# Hi'))->toBeInstanceOf(Document::class);
});

it('returns a rendered result with toc and frontmatter', function () {
    $rendered = Carve::render("---yaml\ntitle: Hello\ntags: [a, b]\n---\n\n# Hello /Carve/\n\n## Second\n\ntext");

    expect($rendered)->toBeInstanceOf(RenderedCarve::class)
        ->and($rendered->html)->toContain('<h1>Hello <em>Carve</em></h1>')->not->toContain('title: Hello')
        ->and($rendered->toc())->toBe([
            ['level' => 1, 'text' => 'Hello Carve', 'html' => 'Hello <em>Carve</em>', 'id' => 'Hello-Carve'],
            ['level' => 2, 'text' => 'Second', 'html' => 'Second', 'id' => 'Second'],
        ])
        ->and($rendered->hasFrontmatter())->toBeTrue()
        ->and($rendered->meta())->toBe(['title' => 'Hello', 'tags' => ['a', 'b']])
        ->and($rendered->meta('tags.1'))->toBe('b')
        ->and($rendered->meta('missing', 'fallback'))->toBe('fallback');
});

it('does not leak toc or frontmatter into the next render', function () {
    Carve::render("---yaml\ntitle: A\n---\n\n# A");
    $second = Carve::render('no headings');

    expect($second->toc())->toBe([])
        ->and($second->hasFrontmatter())->toBeFalse()
        ->and($second->meta())->toBe([]);
});

it('parses json frontmatter and ignores unknown formats', function () {
    expect(Carve::render("---json\n{\"a\": 1}\n---\n\nx")->meta())->toBe(['a' => 1])
        ->and(Carve::render("---toml\na = 1\n---\n\nx")->meta())->toBe([]);
});

it('keeps a user configured toc extension', function () {
    config()->set('carve.profiles.default.extensions', [['type' => 'table_of_contents', 'position' => 'top', 'min_level' => 2]]);

    $rendered = Carve::render("# One\n\n## Two");

    expect($rendered->toc())->toHaveCount(1)
        ->and($rendered->html)->toStartWith('<nav');
});

it('is htmlable, stringable, arrayable and json serializable', function () {
    $rendered = Carve::render('*x*');

    expect($rendered->toHtml())->toBe($rendered->html)
        ->and((string) $rendered)->toBe($rendered->html)
        ->and($rendered->toArray())->toHaveKeys(['source', 'html', 'toc', 'frontmatter'])
        ->and(json_decode(json_encode($rendered), true))->toBe($rendered->toArray())
        ->and(RenderedCarve::fromArray($rendered->toArray()))->toEqual($rendered);
});

it('renders files and expands includes only inside include_root', function () {
    $without = Carve::renderFile(fixture('includes/main.crv'));
    expect($without->html)->toContain('{{ chapters/one.crv }}');

    config()->set('carve.include_root', fixture('includes'));
    Carve::flush();

    $with = Carve::renderFile(fixture('includes/main.crv'));
    expect($with->html)->toContain('Chapter one')->toContain('<strong>text</strong>')
        ->and(collect($with->toc())->pluck('text')->all())->toBe(['Book', 'Chapter one']);
});

it('rejects unreadable files', function () {
    Carve::renderFile(__DIR__.'/Fixtures/missing.crv');
})->throws(InvalidArgumentException::class, 'Carve file is not readable');

it('converts markdown and html into carve', function () {
    expect(Carve::fromMarkdown("# Title\n\n**bold** and *italic*\n"))->toContain('*bold* and /italic/')
        ->and(Carve::fromHtml('<p><strong>b</strong> <em>i</em></p>'))->toContain('*b* /i/');
});

it('lints source sorted by position', function () {
    $warnings = Carve::lint("fine\n\n**bold** habit");

    expect($warnings)->not->toBeEmpty()
        ->and($warnings[0]->rule)->toBe('markdown-strong-asterisks')
        ->and($warnings[0]->line)->toBe(3)
        ->and(Carve::lint('*bold* is fine'))->toBe([]);
});

describe('cache', function () {
    beforeEach(function () {
        config()->set('carve.cache', ['enabled' => true, 'store' => 'array', 'ttl' => null, 'prefix' => 'carve']);
        Carve::flush();
    });

    it('serves repeated renders from the cache', function () {
        $calls = 0;
        Carve::extend('default', function (CarveConverter $converter) use (&$calls) {
            $converter->addOutputTransformer(function (string $html) use (&$calls): string {
                $calls++;

                return $html;
            });
        });

        $first = Carve::render("# A\n\ntext");
        $second = Carve::render("# A\n\ntext");

        expect($calls)->toBe(1)
            ->and($second)->toEqual($first)
            ->and($second->toc())->toHaveCount(1);
    });

    it('keys entries by profile so profiles never share html', function () {
        $source = "```=html\n<b>raw</b>\n```";

        expect(Carve::toHtml($source, 'trusted'))->toContain('<b>raw</b>')
            ->and(Carve::toHtml($source, 'default'))->not->toContain('<b>raw</b>');
    });

    it('uses a new key when the profile config changes', function () {
        Carve::toHtml('![a](x.png)');
        config()->set('carve.profiles.default.xhtml', true);
        Carve::flush();

        expect(Carve::toHtml('![a](x.png)'))->toContain('/>');
    });

    it('stores entries under the configured prefix', function () {
        config()->set('carve.cache.prefix', 'docs');
        Carve::flush();
        Carve::toHtml('hi');

        $keys = array_keys((fn () => $this->storage)->call(Cache::store('array')->getStore()));

        expect($keys)->toHaveCount(1)
            ->and($keys[0])->toStartWith('docs:');
    });
});
