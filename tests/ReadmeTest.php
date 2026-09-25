<?php

use Illuminate\Support\ServiceProvider;
use JeffersonGoncalves\Carve\CarveServiceProvider;
use JeffersonGoncalves\Carve\Facades\Carve;
use MarkupCarve\Carve\CarveConverter;

it('accepts the extension config shown in the readme', function () {
    config()->set('carve.profiles.default.extensions', [
        'autolink',
        'spoiler',
        'mermaid',
        ['type' => 'table_of_contents', 'position' => 'top', 'min_level' => 2],
        ['type' => 'heading_permalinks', 'symbol' => '#', 'position' => 'after'],
        ['type' => 'external_links', 'target' => '_blank', 'internal_hosts' => ['example.com']],
        ['type' => 'wikilinks', 'url_template' => '/wiki/{page}'],
        ['type' => 'fenced_render', 'language' => 'chart'],
    ]);

    expect(Carve::toHtml("# A\n\n## B\n\n[x](https://other.test)"))
        ->toContain('<nav')
        ->toContain('target="_blank"');
});

it('runs the render listener example', function () {
    Carve::extend('default', function (CarveConverter $converter) {
        $converter->on('render.link', function ($event) {
            $event->getNode()->setAttribute('data-turbo', 'false');
        });
    });

    expect(Carve::toHtml('[x](https://a.test)'))->toContain('data-turbo="false"');
});

it('publishes config and translations under the documented tags', function () {
    expect(ServiceProvider::pathsToPublish(CarveServiceProvider::class, 'carve-config'))->toHaveCount(1)
        ->and(ServiceProvider::pathsToPublish(CarveServiceProvider::class, 'carve-translations'))->toHaveCount(1);
});

it('runs the rendered result example', function () {
    $page = Carve::render(<<<'CARVE'
    ---yaml
    title: Getting started
    tags: [intro, setup]
    ---

    # Getting started

    ## Install
    CARVE);

    expect($page->meta('tags.0'))->toBe('intro')
        ->and($page->toc()[0])->toMatchArray(['level' => 1, 'text' => 'Getting started', 'id' => 'Getting-started']);
});
