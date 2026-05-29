<?php

declare(strict_types=1);

namespace WpCubiCastor\Git;

use function Castor\run;

/**
 * Manages git-flow operations (feature, hotfix, release).
 */
final class GitFlowManager
{
    // -------------------------------------------------------------------------
    // Features
    // -------------------------------------------------------------------------

    public function featureStart(string $name): void
    {
        run('git flow feature start ' . escapeshellarg($name));
    }

    public function featureFinish(string $name): void
    {
        run('git flow feature finish ' . escapeshellarg($name));
    }

    // -------------------------------------------------------------------------
    // Hotfix
    // -------------------------------------------------------------------------

    public function hotfixStart(string $version): void
    {
        run('git flow hotfix start ' . escapeshellarg($version));
    }

    public function hotfixFinish(string $version): void
    {
        run('git flow hotfix finish ' . escapeshellarg($version));
    }

    // -------------------------------------------------------------------------
    // Release
    // -------------------------------------------------------------------------

    public function releaseStart(string $version): void
    {
        run('git flow release start ' . escapeshellarg($version));
    }

    public function releaseFinish(string $version): void
    {
        run('git flow release finish ' . escapeshellarg($version));
    }

    // -------------------------------------------------------------------------
    // Git helpers
    // -------------------------------------------------------------------------

    /**
     * Initialises a Git repository and creates the initial commit.
     */
    public function init(string $projectRoot, string $commitMessage = 'Initial commit'): void
    {
        run("git -C {$projectRoot} init");
        run("git -C {$projectRoot} add -A");
        run("git -C {$projectRoot} commit -m " . escapeshellarg($commitMessage));
    }

    /**
     * Returns the short commit hash for a given revision.
     */
    public function resolveCommit(string $gitRevision): ?string
    {
        $process = run(
            'git rev-parse --short ' . escapeshellarg($gitRevision),
            quiet: true,
            allowFailure: true
        );

        return $process->isSuccessful() ? trim($process->getOutput()) : null;
    }

    /**
     * Resolves the tag associated with a revision (direct tag, release_x.y.z or hotfix_x.y.z branch).
     */
    public function resolveTag(string $gitRevision): ?string
    {
        $isTag = run(
            'git show-ref --verify --quiet refs/tags/' . escapeshellarg($gitRevision),
            quiet: true,
            allowFailure: true
        )->isSuccessful();

        if ($isTag) {
            return $gitRevision;
        }

        if (str_contains($gitRevision, 'release_')) {
            return str_replace('release_', '', $gitRevision);
        }

        if (str_contains($gitRevision, 'hotfix_')) {
            return str_replace('hotfix_', '', $gitRevision);
        }

        return null;
    }

    /**
     * Extracts a Git archive into a temporary directory.
     */
    public function extractArchive(string $gitRevision, string $targetDirectory): void
    {
        @mkdir($targetDirectory, 0755, true);

        $basename = basename($targetDirectory);
        $parent   = dirname($targetDirectory);

        run("git archive --format=tar --prefix={$basename}/ {$gitRevision} | (cd {$parent} && tar xf -)");
    }
}
