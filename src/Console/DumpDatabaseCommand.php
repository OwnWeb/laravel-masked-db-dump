<?php

namespace BeyondCode\LaravelMaskedDumper\Console;

use Illuminate\Console\Command;
use BeyondCode\LaravelMaskedDumper\LaravelMaskedDump;

class DumpDatabaseCommand extends Command
{
    protected const PERMISSION_BITS = 0777;

    protected $signature = 'db:masked-dump {output} {--definition=default} {--gzip}';

    protected $description = 'Create a new database dump';

    public function handle()
    {
        $definition = config('masked-dump.' . $this->option('definition'));
        $definition = is_callable($definition) ? call_user_func($definition) : $definition;
        $definition->load();

        $this->info('Starting Database dump');

        $outputPath = $this->option('gzip') ? $this->argument('output') . '.gz' : $this->argument('output');
        $partialOutputPath = $outputPath . '.part';
        $stream = $this->openPartialDump($partialOutputPath, $outputPath);

        try {
            $dumper = new LaravelMaskedDump($definition, $this->output);
            $dumper->dumpToStream($stream);
        } catch (\Throwable $exception) {
            fclose($stream);
            unlink($partialOutputPath);

            throw $exception;
        }

        $this->publish($stream, $partialOutputPath, $outputPath);

        $this->output->writeln('');
        $this->info('Wrote database dump to ' . $outputPath);
    }

    /**
     * @param resource $stream
     */
    protected function publish($stream, string $partialOutputPath, string $outputPath)
    {
        if (! fclose($stream)) {
            unlink($partialOutputPath);

            throw new \RuntimeException('Could not finish writing the database dump to ' . $partialOutputPath . '. Is the disk full?');
        }

        rename($partialOutputPath, $outputPath);
    }

    /**
     * @return resource
     */
    protected function openPartialDump(string $partialOutputPath, string $outputPath)
    {
        $stream = $this->option('gzip') ? gzopen($partialOutputPath, 'w9') : fopen($partialOutputPath, 'w');

        if (file_exists($outputPath)) {
            chmod($partialOutputPath, fileperms($outputPath) & self::PERMISSION_BITS);
        }

        return $stream;
    }
}
