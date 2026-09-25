<?php

namespace JeffersonGoncalves\Carve\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\Carve\Facades\Carve;
use JeffersonGoncalves\Carve\RenderedCarve;
use Stringable;

/**
 * Stores Carve source, reads it back rendered.
 *
 *     protected $casts = [
 *         'body' => AsCarve::class,
 *         'comment' => AsCarve::class.':comment',
 *     ];
 *
 *     {{ $post->body }}          // rendered HTML
 *     $post->body->source        // the stored markup
 *     $post->body->toc()
 *
 * @implements CastsAttributes<RenderedCarve|null, RenderedCarve|Stringable|string|null>
 */
class AsCarve implements CastsAttributes
{
    public function __construct(private readonly ?string $profile = null) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?RenderedCarve
    {
        return $value === null ? null : Carve::render((string) $value, $this->profile);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof RenderedCarve => $value->source,
            default => (string) $value,
        };
    }
}
