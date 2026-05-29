<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;
use function Castor\io;

// ---------------------------------------------------------------------------
// feature:start / feature:finish
// ---------------------------------------------------------------------------

#[AsTask(namespace: 'feature', name: 'start', description: 'Démarre une branche feature (git flow)')]
function feature_start(string $name): void
{
    wcc_git_flow()->featureStart($name);
    io()->success("Feature '{$name}' démarrée.");
}

#[AsTask(namespace: 'feature', name: 'finish', description: 'Termine une branche feature (git flow)')]
function feature_finish(string $name): void
{
    wcc_git_flow()->featureFinish($name);
    io()->success("Feature '{$name}' terminée.");
}

// ---------------------------------------------------------------------------
// hotfix:start / hotfix:finish
// ---------------------------------------------------------------------------

#[AsTask(namespace: 'hotfix', name: 'start', description: 'Démarre un hotfix (incrémente patch par défaut)')]
function hotfix_start(?string $semversion = null, string $type = 'patch'): void
{
    $version = $semversion ?? (string) wcc_semver()->bump($type);
    wcc_git_flow()->hotfixStart($version);
    io()->success("Hotfix {$version} démarré.");
}

#[AsTask(namespace: 'hotfix', name: 'finish', description: 'Termine un hotfix et crée le tag de version')]
function hotfix_finish(?string $semversion = null, string $type = 'patch'): void
{
    $version = $semversion ?? (string) wcc_semver()->bump($type);
    wcc_git_flow()->hotfixFinish($version);
    io()->success("Hotfix {$version} terminé.");
}

// ---------------------------------------------------------------------------
// release:start / release:finish
// ---------------------------------------------------------------------------

#[AsTask(namespace: 'release', name: 'start', description: 'Démarre une release (incrémente minor par défaut)')]
function release_start(?string $semversion = null, string $type = 'minor'): void
{
    $version = $semversion ?? (string) wcc_semver()->bump($type);
    wcc_git_flow()->releaseStart($version);
    io()->success("Release {$version} démarrée.");
}

#[AsTask(namespace: 'release', name: 'finish', description: 'Termine une release et crée le tag de version')]
function release_finish(?string $semversion = null, string $type = 'minor'): void
{
    $version = $semversion ?? (string) wcc_semver()->bump($type);
    wcc_git_flow()->releaseFinish($version);
    io()->success("Release {$version} terminée.");
}
