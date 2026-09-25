<?php

namespace JeffersonGoncalves\Carve\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use JeffersonGoncalves\Carve\CarveManager;

/**
 * <x-carve :source="$post->body" profile="comment" />
 *
 * <x-carve>
 *     # Hello {{ $name }}
 * </x-carve>
 *
 * Slot content is dedented, so it can follow the template's indentation.
 */
class Carve extends Component
{
    public function __construct(
        public ?string $source = null,
        public ?string $profile = null,
    ) {}

    public function render(): string
    {
        // A view name, not a closure: a closure's string is compiled as
        // Blade, which would turn rendered user content into template code.
        return 'carve::components.carve';
    }

    public function toHtml(mixed $slot): string
    {
        // {{ }} in the slot has already HTML-escaped its value. Carve escapes
        // text itself, so decode first: otherwise "&lt;" renders as "&amp;lt;"
        // and "&#039;" parses as a #039 tag.
        $source = $this->source ?? html_entity_decode(self::dedent((string) $slot), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return app(CarveManager::class)->toHtml($source, $this->profile);
    }

    public static function dedent(string $text): string
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $text));

        // Drop blank lines around the content so the first line's indent is real.
        while ($lines !== [] && trim($lines[0]) === '') {
            array_shift($lines);
        }
        while ($lines !== [] && trim((string) end($lines)) === '') {
            array_pop($lines);
        }

        $indent = null;
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $indent = min($indent ?? PHP_INT_MAX, strlen($line) - strlen(ltrim($line, " \t")));
            }
        }

        return implode("\n", array_map(fn (string $line): string => substr($line, $indent ?? 0), $lines));
    }
}
