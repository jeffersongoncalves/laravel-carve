<?php

namespace JeffersonGoncalves\Carve\View;

use Illuminate\Contracts\View\Engine;
use JeffersonGoncalves\Carve\CarveManager;

/**
 * Renders `.crv` view files. View data is not interpolated: a Carve view is
 * a static document. Use the Blade component to mix Carve with variables.
 */
class CarveEngine implements Engine
{
    public function __construct(
        private readonly CarveManager $carve,
        private readonly ?string $profile = null,
    ) {}

    /**
     * @param  string  $path
     * @param  array<string, mixed>  $data
     */
    public function get($path, array $data = []): string
    {
        return $this->carve->renderFile($path, $this->profile)->html;
    }
}
