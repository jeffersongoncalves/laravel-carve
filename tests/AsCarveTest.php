<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use JeffersonGoncalves\Carve\Casts\AsCarve;
use JeffersonGoncalves\Carve\Facades\Carve;
use JeffersonGoncalves\Carve\RenderedCarve;

class CarvePost extends Model
{
    protected $table = 'posts';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'body' => AsCarve::class,
        'comment' => AsCarve::class.':comment',
    ];
}

beforeEach(function () {
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->text('body')->nullable();
        $table->text('comment')->nullable();
    });
});

it('stores the source and reads it back rendered', function () {
    $post = CarvePost::create(['body' => "# Title\n\n*bold*"]);

    expect($post->getRawOriginal('body'))->toBe("# Title\n\n*bold*");

    $post = CarvePost::find($post->id);

    expect($post->body)->toBeInstanceOf(RenderedCarve::class)
        ->and($post->body->source)->toBe("# Title\n\n*bold*")
        ->and($post->body->html)->toContain('<strong>bold</strong>')
        ->and($post->body->toc())->toHaveCount(1);
});

it('uses the profile given as cast argument', function () {
    $post = CarvePost::create(['comment' => '# Not a heading']);

    expect($post->fresh()->comment->html)->not->toContain('<h1');
});

it('keeps null as null', function () {
    $post = CarvePost::create(['body' => null]);

    expect($post->fresh()->body)->toBeNull();
});

it('accepts a rendered value and stringables when setting', function () {
    $post = CarvePost::create(['body' => Carve::render('*a*')]);
    expect($post->getAttributes()['body'])->toBe('*a*');

    $post->body = str('/b/');
    $post->save();

    expect($post->fresh()->body->html)->toContain('<em>b</em>');
});

it('prints unescaped html in blade and full data in json', function () {
    $post = CarvePost::create(['body' => '*a*'])->fresh();

    expect(Blade::render('{{ $post->body }}', ['post' => $post]))->toContain('<strong>a</strong>')
        ->and($post->toArray()['body'])->toMatchArray(['source' => '*a*']);
});
