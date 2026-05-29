<?php

declare(strict_types=1);

namespace WpCubiCastor\Infrastructure;

use Symfony\Component\Process\Process;
use function Castor\run;

/**
 * Wrapper around WP-CLI.
 * Detects the local binary (vendor/bin/wp) or falls back to the global one.
 */
final class WpCli
{
    private readonly string $binary;

    public function __construct(private readonly string $projectRoot)
    {
        $local = $projectRoot . '/vendor/bin/wp';
        $this->binary = file_exists($local) ? $local : 'wp';
    }

    /**
     * Runs a WP-CLI command and returns the Process.
     *
     * @param string ...$args WP-CLI arguments (e.g. 'plugin', 'activate', '--all')
     */
    public function run(string ...$args): Process
    {
        return run($this->buildCommand(...$args));
    }

    /**
     * Runs without throwing on failure.
     */
    public function runSilent(string ...$args): Process
    {
        return run($this->buildCommand(...$args), quiet: true, allowFailure: true);
    }

    /**
     * Returns the trimmed stdout output of a WP-CLI command.
     */
    public function getOutput(string ...$args): string
    {
        return trim($this->runSilent(...$args)->getOutput());
    }

    public function getBinary(): string
    {
        return $this->binary;
    }

    private function buildCommand(string ...$args): string
    {
        $parts = array_map('escapeshellarg', $args);
        return $this->binary . ' ' . implode(' ', $parts);
    }
}
