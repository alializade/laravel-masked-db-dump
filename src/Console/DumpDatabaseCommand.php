<?php

namespace BeyondCode\LaravelMaskedDumper\Console;

use Illuminate\Console\Command;
use BeyondCode\LaravelMaskedDumper\LaravelMaskedDump;

class DumpDatabaseCommand extends Command
{
    protected $signature = 'db:masked-dump {output} {--definition=default} {--gzip}';

    protected $description = 'Create a new database dump';

    public function handle()
    {
        $definition = config('masked-dump.' . $this->option('definition'));
        $definition->load();

        $this->info('Starting Database dump');

        $dumper = new LaravelMaskedDump($definition, $this->output);
        $dump = $dumper->dump();

        $this->output->writeln('');
        $this->writeOutput($dump);
    }

    protected function writeOutput(string $dump)
    {
        $dir = 'storage/app/';
        $fileName = 'Dump_' . now()->format('Y-m-d_H-i') .'-'. str($this->argument('output'))->trim();

        $filePath = $dir . $fileName;

        if ($this->option('gzip')) {
            $gz = gzopen($filePath . '.gz', 'w9');
            gzwrite($gz, $dump);
            gzclose($gz);

            $this->info('Wrote database dump to ' . $filePath . '.gz');
        } else {
            file_put_contents($filePath, $dump);
            $this->info('Wrote database dump to ' . $filePath);
        }
    }
}
