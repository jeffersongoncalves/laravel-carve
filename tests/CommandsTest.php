<?php

use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->out = sys_get_temp_dir().'/laravel-carve-'.uniqid().'.out';
});

afterEach(function () {
    @unlink($this->out);
});

it('renders a file as html', function () {
    $this->artisan('carve:render', ['path' => fixture('views/docs/intro.crv')])
        ->expectsOutputToContain('<h1>Intro</h1>')
        ->assertSuccessful();
});

it('renders a file in other formats', function (string $format, string $expected) {
    Artisan::call('carve:render', ['path' => fixture('views/docs/intro.crv'), '--format' => $format]);

    expect(Artisan::output())->toContain($expected);
})->with([
    'text' => ['text', 'Welcome to Carve views.'],
    'markdown' => ['markdown', 'Welcome to *Carve* views.'],
    'ansi' => ['ansi', "\e["],
]);

it('renders with a profile and writes to a file', function () {
    $this->artisan('carve:render', ['path' => fixture('views/docs/intro.crv'), '--profile' => 'comment', '--output' => $this->out])
        ->assertSuccessful();

    expect(file_get_contents($this->out))->not->toContain('<h1');
});

it('fails on an unknown format or missing file', function () {
    $this->artisan('carve:render', ['path' => fixture('views/docs/intro.crv'), '--format' => 'pdf'])->assertFailed();
    $this->artisan('carve:render', ['path' => 'missing.crv'])->assertFailed();
});

it('converts markdown and html files', function () {
    Artisan::call('carve:convert', ['path' => fixture('readme.md')]);
    expect(Artisan::output())->toContain('*bold* and /italic/');

    Artisan::call('carve:convert', ['path' => fixture('page.html')]);
    expect(Artisan::output())->toContain('*bold* /italic/');
});

it('converts with an explicit format to a file', function () {
    $this->artisan('carve:convert', ['path' => fixture('readme.md'), '--from' => 'markdown', '--output' => $this->out])
        ->assertSuccessful();

    expect(file_get_contents($this->out))->toContain('# Title');
});

it('fails to convert unknown formats and missing files', function () {
    $this->artisan('carve:convert', ['path' => fixture('views/docs/intro.crv')])->assertFailed();
    $this->artisan('carve:convert', ['path' => 'missing.md'])->assertFailed();
});

it('lints files and directories', function () {
    $this->artisan('carve:lint', ['paths' => [fixture('lint')]])
        ->expectsOutputToContain('markdown-strong-asterisks')
        ->assertFailed();

    $this->artisan('carve:lint', ['paths' => [fixture('lint/good.crv')]])
        ->expectsOutputToContain('No Carve problems found.')
        ->assertSuccessful();

    $this->artisan('carve:lint', ['paths' => ['missing-dir']])
        ->expectsOutputToContain('Skipping [missing-dir]')
        ->assertSuccessful();
});
