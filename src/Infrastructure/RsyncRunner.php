<?php

declare(strict_types=1);

namespace WpCubiCastor\Infrastructure;

use function Castor\run;

/**
 * Executes rsync transfers from an RsyncOptions object.
 */
final class RsyncRunner
{
    /**
     * Runs rsync with a dry-run then asks for confirmation before the real sync.
     * Returns true if the sync was performed, false if cancelled.
     */
    public function syncWithConfirm(RsyncOptions $options, string $confirmMessage = 'Lancer la synchronisation réelle ?'): bool
    {
        $this->sync($options->withDryRun(true));

        if (!\Castor\io()->confirm($confirmMessage, false)) {
            return false;
        }

        $this->sync($options->withDryRun(false));
        return true;
    }

    /**
     * Runs rsync directly without confirmation.
     */
    public function sync(RsyncOptions $options): void
    {
        run($this->buildCommand($options));
    }

    private function buildCommand(RsyncOptions $options): string
    {
        $from = $this->buildPath($options->fromHost, $options->fromUser, $options->fromPath);
        $to   = $this->buildPath($options->toHost,   $options->toUser,   $options->toPath);

        $parts = [
            'rsync',
            '--recursive',
            '--checksum',
            '--compress',
            '--copy-links',
            '--stats',
            "--rsh='ssh -p {$options->port}'",
            '--exclude-vcs',
        ];

        if ($options->verbose) {
            $parts[] = '--verbose';
            $parts[] = '--itemize-changes';
            $parts[] = '--progress';
        } else {
            $parts[] = '--quiet';
        }

        if ($options->delete) {
            $parts[] = '--delete';
        }

        if ($options->chmod !== null) {
            $parts[] = '--perms';
            $parts[] = "--chmod={$options->chmod}";
        }

        if ($options->dryRun) {
            $parts[] = '--dry-run';
        }

        foreach ($options->extraExcludes as $exclude) {
            $parts[] = '--exclude=' . escapeshellarg($exclude);
        }

        if ($options->excludeFrom !== null && file_exists($options->excludeFrom)) {
            $parts[] = '--exclude-from=' . escapeshellarg($options->excludeFrom);
        } else {
            $parts[] = '--exclude=.gitkeep';
        }

        $parts[] = $from;
        $parts[] = $to;

        return implode(' ', $parts);
    }

    private function buildPath(?string $host, ?string $user, string $path): string
    {
        $path = rtrim($path, '/\\') . '/';
        return $host !== null ? "{$user}@{$host}:{$path}" : $path;
    }
}
