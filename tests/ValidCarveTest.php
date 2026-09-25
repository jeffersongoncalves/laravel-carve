<?php

use Illuminate\Support\Facades\Validator;
use JeffersonGoncalves\Carve\Rules\ValidCarve;

function carveErrors(mixed $value, ValidCarve $rule): array
{
    return Validator::make(['body' => $value], ['body' => [$rule]])->errors()->get('body');
}

it('passes valid carve and empty values', function (mixed $value) {
    expect(carveErrors($value, new ValidCarve))->toBe([]);
})->with(['# Title\n\n*bold*', '', null]);

it('fails non strings', function () {
    expect(carveErrors(['x'], new ValidCarve))->toBe(['The body field must be a string of Carve markup.']);
});

it('fails markup the preset does not allow', function () {
    expect(carveErrors("# Heading\n\n![img](x.png)", ValidCarve::preset('comment')))
        ->toBe(['The body field uses markup that is not allowed here: heading, image.'])
        ->and(carveErrors('*fine* [link](https://x.test)', ValidCarve::preset('comment')))->toBe([]);
});

it('fails parse warnings in strict mode only', function () {
    $source = 'See [the docs][missing].';

    expect(carveErrors($source, new ValidCarve))->toBe([])
        ->and(carveErrors($source, (new ValidCarve)->strict()))
        ->toBe(["The body field has a Carve problem on line 1: Undefined reference 'missing'"]);
});

it('fails lint findings when asked', function () {
    expect(carveErrors('**bold**', new ValidCarve))->toBe([])
        ->and(carveErrors('**bold**', (new ValidCarve)->lint())[0])->toStartWith('The body field has a Carve problem on line 1: `**bold**`');
});

it('limits the length in characters', function () {
    $rule = (new ValidCarve)->maxLength(3);

    expect(carveErrors('ção', $rule))->toBe([])
        ->and(carveErrors('ações', $rule))->toBe(['The body field must not be greater than 3 characters.']);
});

it('fails input past the preset length limit', function () {
    expect(carveErrors(str_repeat('a', 10_001), ValidCarve::preset('minimal')))->toHaveCount(1);
});

it('translates messages', function () {
    app()->setLocale('pt_BR');

    expect(carveErrors(['x'], new ValidCarve))->toBe(['O campo body deve ser um texto em Carve.']);
});
