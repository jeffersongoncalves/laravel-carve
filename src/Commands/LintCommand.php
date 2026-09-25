<?php

namespace JeffersonGoncalves\Carve\Commands;

use Illuminate\Console\Command;
use JeffersonGoncalves\Carve\CarveManager;
use Symfony\Component\Finder\Finder;

class LintCommand extends Command
{
    protected $signature = 'carve:lint
        {paths* : Files or directories (directories are searched for *.crv)}';

    protected $description = 'Report Carve constructs that probably do not mean what the author intended';

    public function handle(CarveManager $carve): int
    {
        $rows = [];

        foreach ($this->files() as $file) {
            foreach ($carve->lint((string) file_get_contents($file)) as $warning) {
                $rows[] = ["{$file}:{$warning->line}:{$warning->column}", $warning->rule, $warning->message];
            }
        }

        if ($rows === []) {
            $this->components->info('No Carve problems found.');

            return self::SUCCESS;
        }

        $this->table(['Location', 'Rule', 'Message'], $rows);
        $this->components->error(sprintf('%d Carve problem(s) found.', count($rows)));

        return self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        $files = [];

        foreach ((array) $this->argument('paths') as $path) {
            if (is_dir($path)) {
                foreach (Finder::create()->files()->in($path)->name('*.crv')->sortByName() as $file) {
                    $files[] = $file->getPathname();
                }
            } elseif (is_file($path)) {
                $files[] = $path;
            } else {
                $this->components->warn("Skipping [{$path}]: not found.");
            }
        }

        return $files;
    }
}
