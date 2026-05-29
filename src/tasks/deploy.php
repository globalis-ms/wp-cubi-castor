<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

// ---------------------------------------------------------------------------
// deploy
// ---------------------------------------------------------------------------

#[AsTask(
    name: 'deploy',
    description: 'Déploie une révision Git vers un environnement distant (rsync)',
)]
function deploy(
    string $environment,
    string $gitRevision,
    bool   $ignoreAssets   = false,
    bool   $ignoreComposer = false,
): void {
    wcc_deployer()->deploy($environment, $gitRevision, $ignoreAssets, $ignoreComposer);
}

// ---------------------------------------------------------------------------
// deploy:setup
// ---------------------------------------------------------------------------

#[AsTask(
    namespace: 'deploy',
    name: 'setup',
    description: 'Initialise la structure de l\'environnement distant (première fois)',
)]
function deploy_setup(string $environment): void
{
    wcc_deployer()->setup($environment);
}

// ---------------------------------------------------------------------------
// media:dump
// ---------------------------------------------------------------------------

#[AsTask(
    namespace: 'media',
    name: 'dump',
    description: 'Récupère les médias depuis le serveur distant en local',
)]
function media_dump(string $environment, bool $delete = false): void
{
    wcc_media()->dump($environment, $delete);
}

// ---------------------------------------------------------------------------
// media:push
// ---------------------------------------------------------------------------

#[AsTask(
    namespace: 'media',
    name: 'push',
    description: 'Envoie les médias locaux vers le serveur distant',
)]
function media_push(string $environment, bool $delete = false): void
{
    wcc_media()->push($environment, $delete);
}
