<?php

declare(strict_types=1);

namespace WpCubiCastor\Deploy;

use WpCubiCastor\Config\ConfigManager;
use WpCubiCastor\Infrastructure\RsyncOptions;
use WpCubiCastor\Infrastructure\RsyncRunner;

/**
 * Synchronises WordPress media between local and remote.
 */
final class MediaSync
{
    private const UPLOADS_PATH = 'web/app/uploads';

    public function __construct(
        private readonly ConfigManager $config,
        private readonly RsyncRunner   $rsync,
        private readonly string        $projectRoot,
    ) {}

    /**
     * Downloads media from the remote server to local.
     */
    public function dump(string $environment, bool $delete = false): void
    {
        $this->sync($environment, direction: 'dump', delete: $delete);
    }

    /**
     * Uploads local media to the remote server.
     */
    public function push(string $environment, bool $delete = false): void
    {
        $this->sync($environment, direction: 'push', delete: $delete);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function sync(string $environment, string $direction, bool $delete): void
    {
        $config     = $this->config->get($environment);
        $localPath  = "{$this->projectRoot}/" . self::UPLOADS_PATH;
        $remotePath = rtrim((string) $config['REMOTE_PATH'], '/') . '/' . self::UPLOADS_PATH;

        @mkdir($localPath, 0755, true);

        $options = match ($direction) {
            'dump' => new RsyncOptions(
                fromHost: (string) $config['REMOTE_HOSTNAME'],
                fromUser: (string) $config['REMOTE_USERNAME'],
                fromPath: $remotePath,
                toHost:   null,
                toUser:   null,
                toPath:   $localPath,
                port:     (int) ($config['REMOTE_PORT'] ?? 22),
                delete:   $delete,
            ),
            'push' => new RsyncOptions(
                fromHost: null,
                fromUser: null,
                fromPath: $localPath,
                toHost:   (string) $config['REMOTE_HOSTNAME'],
                toUser:   (string) $config['REMOTE_USERNAME'],
                toPath:   $remotePath,
                port:     (int) ($config['REMOTE_PORT'] ?? 22),
                delete:   $delete,
            ),
            default => throw new \InvalidArgumentException("Direction invalide : {$direction}"),
        };

        $this->rsync->syncWithConfirm($options, 'Lancer la synchronisation réelle ?');
    }
}
