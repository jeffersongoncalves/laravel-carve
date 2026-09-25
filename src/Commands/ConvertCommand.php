<?php

namespace JeffersonGoncalves\Carve\Commands;

use Illuminate\Console\Command;
use JeffersonGoncalves\Carve\CarveManager;

class ConvertCommand extends Command
{
    protected $signature = 'carve:convert
        {path : The Markdown or HTML file to convert}
        {--from= : markdown or html (guessed from the file extension)}
        {--output= : Write to this file instead of the console}';

    protected $description = 'Convert a Markdown or HTML file into Carve';

    public function handle(CarveManager $carve): int
    {
        $path = $this->argument('path');

        if (! is_string($path)) {
            return self::FAILURE;
        }

        if (! is_file($path) || ! is_readable($path)) {
            $this->components->error("File [{$path}] is not readable.");

            return self::FAILURE;
        }

        $from = $this->option('from') ?: match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'md', 'markdown' => 'markdown',
            'html', 'htm' => 'html',
            default => null,
        };

        $source = (string) file_get_contents($path);

        $output = match ($from) {
            'markdown' => $carve->fromMarkdown($source),
            'html' => $carve->fromHtml($source),
            default => null,
        };

        if ($output === null) {
            $this->components->error('Cannot tell the input format. Pass --from=markdown or --from=html.');

            return self::FAILURE;
        }

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
