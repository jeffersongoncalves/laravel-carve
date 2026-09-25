<?php

namespace JeffersonGoncalves\Carve\Commands;

use Illuminate\Console\Command;
use JeffersonGoncalves\Carve\CarveManager;

class RenderCommand extends Command
{
    protected $signature = 'carve:render
        {path : The .crv file to render}
        {--format=html : html, text, markdown or ansi}
        {--profile= : The render profile (defaults to carve.default)}
        {--output= : Write to this file instead of the console}';

    protected $description = 'Render a Carve file as HTML, plain text, Markdown or ANSI';

    public function handle(CarveManager $carve): int
    {
        $path = $this->argument('path');

        if (! is_string($path)) {
            return self::FAILURE;
        }
        $profile = $this->option('profile');
        $format = $this->option('format');

        if (! is_file($path) || ! is_readable($path)) {
            $this->components->error("File [{$path}] is not readable.");

            return self::FAILURE;
        }

        $converter = $carve->profile(is_string($profile) ? $profile : null);
        $source = (string) file_get_contents($path);

        $output = match ($format) {
            'html' => $converter->renderFile($path)->html,
            'text' => $converter->toText($source),
            'markdown' => $converter->toMarkdown($source),
            'ansi' => $converter->toAnsi($source),
            default => null,
        };

        if ($output === null) {
            $this->components->error('Unknown format. Use html, text, markdown or ansi.');

            return self::FAILURE;
        }

        return $this->write($output);
    }

    private function write(string $output): int
    {
        $target = $this->option('output');

        if (is_string($target) && $target !== '') {
            file_put_contents($target, $output);
            $this->components->info("Written to [{$target}].");
        } else {
            $this->output->write($output);
        }

        return self::SUCCESS;
    }
}
