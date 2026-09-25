<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use JeffersonGoncalves\Carve\View\Components\Carve as CarveComponent;

it('renders the @carve directive', function () {
    expect(Blade::render('@carve($source)', ['source' => 'Hi *there*']))
        ->toContain('<strong>there</strong>');
});

it('renders the @carve directive with a profile', function () {
    $source = "```=html\n<b>raw</b>\n```";

    expect(Blade::render("@carve(\$source, 'trusted')", ['source' => $source]))->toContain('<b>raw</b>')
        ->and(Blade::render('@carve($source)', ['source' => $source]))->not->toContain('<b>raw</b>');
});

it('renders the @carveText directive escaped', function () {
    expect(Blade::render('@carveText($source)', ['source' => '*a* <b>']))
        ->toBe('a &lt;b&gt;');
});

it('renders the component from a source attribute', function () {
    expect(Blade::render('<x-carve :source="$body" />', ['body' => '/em/']))
        ->toContain('<em>em</em>');
});

it('renders the component slot, dedented', function () {
    $html = Blade::render(<<<'BLADE'
        <div>
            <x-carve>
                # Title

                    code block? no, a paragraph indented relative to the heading
            </x-carve>
        </div>
        BLADE);

    expect($html)->toContain('<h1>Title</h1>');
});

it('renders the component with a profile', function () {
    expect(Blade::render('<x-carve profile="comment" source="# Heading" />'))
        ->not->toContain('<h1');
});

it('never compiles rendered user content as blade', function () {
    $html = Blade::render('<x-carve :source="$body" />', ['body' => '{{ 1 + 1 }} @php echo "pwned"; @endphp']);

    expect($html)->not->toContain('pwned"')
        ->and($html)->not->toMatch('/(^|[^{])2([^}]|$)/')
        ->and($html)->toContain('{{ 1 + 1 }}');
});

it('interpolates blade variables inside the slot, escaped exactly once', function () {
    $html = Blade::render('<x-carve>Hello {{ $name }}</x-carve>', ['name' => "<script> & Tom's"]);

    expect($html)->toContain('<p>Hello &lt;script&gt; &amp; Tom’s</p>')
        ->not->toContain('<script>')
        ->not->toContain('class="tag"');
});

it('keeps raw html out even when a decoded slot tries to inject it', function () {
    $html = Blade::render('<x-carve>{{ $name }}</x-carve>', ['name' => '<img src=x onerror=alert(1)>']);

    expect($html)->not->toContain('<img');
});

it('dedents text', function () {
    expect(CarveComponent::dedent("\n\n    a\n      b\n\n    c\n  \n"))->toBe("a\n  b\n\nc")
        ->and(CarveComponent::dedent("\r\n\tx\r\n\ty"))->toBe("x\ny")
        ->and(CarveComponent::dedent(''))->toBe('');
});

it('renders .crv views', function () {
    expect(view('docs.intro')->render())
        ->toContain('<h1>Intro</h1>')
        ->toContain('<em>Carve</em>');
});

it('renders .crv views with the configured view profile', function () {
    config()->set('carve.views.profile', 'comment');
    app()->forgetInstance('view');
    app()->forgetInstance('view.engine.resolver');

    expect(view('docs.intro')->render())->not->toContain('<h1');
});

it('adds Str and Stringable macros', function () {
    expect(Str::carve('*x*'))->toContain('<strong>x</strong>')
        ->and(Str::carveText('*x*'))->toBe('x')
        ->and(Str::of('*x*')->carve()->toString())->toContain('<strong>x</strong>')
        ->and(Str::of("# Title\n\nA long /body/")->carveText()->squish()->limit(12)->toString())->toBe('Title A long...')
        ->and(Str::carve('# H', 'comment'))->not->toContain('<h1');
});
