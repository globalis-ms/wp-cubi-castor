<?php

declare(strict_types=1);

namespace WpCubiCastor\Deploy;

use WpCubiCastor\Build\Builder;
use WpCubiCastor\Config\ConfigManager;
use WpCubiCastor\Infrastructure\RsyncOptions;
use WpCubiCastor\Infrastructure\RsyncRunner;
use WpCubiCastor\WordPress\WordPressManager;
use function Castor\io;
use function Castor\run;

/**
 * Orchestrates the deployment of a Git revision to a remote environment.
 */
final class Deployer
{
    public function __construct(
        private readonly ConfigManager    $config,
        private readonly Builder          $builder,
        private readonly RsyncRunner      $rsync,
        private readonly WordPressManager $wp,
        private readonly string           $projectRoot,
    ) {}

    /**
     * Deploys a Git revision to a remote environment.
     * Workflow: dry-run → confirmation → rsync → webhooks → ping.
     */
    public function deploy(
        string $environment,
        string $gitRevision,
        bool   $ignoreAssets   = false,
        bool   $ignoreComposer = false,
    ): void {
        $this->checkWordPressVersion();

        io()->title("Déploiement de {$gitRevision} vers {$environment}");

        $configFile = $this->config->getConfigPath($environment);
        $this->config->configure($environment, onlyMissing: file_exists($configFile));

        $config   = $this->config->get($environment);
        $buildDir = sys_get_temp_dir() . '/wp-cubi-build-' . uniqid();

        try {
            $this->extractArchive($gitRevision, $buildDir);
            $this->builder->buildAll($environment, $ignoreAssets, $ignoreComposer);
            $this->writeDeployState("{$buildDir}/deploy", $gitRevision);

            $rsyncOptions = $this->buildRsyncOptions(
                buildDir:       $buildDir,
                config:         $config,
                ignoreAssets:   $ignoreAssets,
                ignoreComposer: $ignoreComposer,
            );

            $deployed = $this->rsync->syncWithConfirm($rsyncOptions, 'Lancer le déploiement réel ?');
        } finally {
            run("rm -rf " . escapeshellarg($buildDir));
        }

        if ($deployed) {
            $this->runPostDeployWebhooks((string) $config['WEB_SCHEME'], (string) $config['WEB_DOMAIN'], (string) ($config['WEB_PATH'] ?? ''));
            io()->success("Déploiement de {$gitRevision} vers {$environment} terminé.");
        }
    }

    /**
     * Sets up the remote directory structure for the first deployment.
     */
    public function setup(string $environment): void
    {
        io()->title("Initialisation de l'environnement distant : {$environment}");

        $this->config->configure($environment);
        $config   = $this->config->get($environment);
        $buildDir = sys_get_temp_dir() . '/wp-cubi-setup-' . uniqid();

        try {
            foreach (['config', 'web/app', 'web/app/uploads', 'log'] as $dir) {
                @mkdir("{$buildDir}/{$dir}", 0755, true);
            }

            $this->builder->buildConfig($environment);
            $this->wp->generateSaltKeys($buildDir);

            $options = new RsyncOptions(
                fromHost: null,
                fromUser: null,
                fromPath: $buildDir,
                toHost:   (string) $config['REMOTE_HOSTNAME'],
                toUser:   (string) $config['REMOTE_USERNAME'],
                toPath:   (string) $config['REMOTE_PATH'],
                port:     (int) ($config['REMOTE_PORT'] ?? 22),
                delete:   true,
                chmod:    'Du=rwx,Dgo=rx,Fu=rw,Fgo=r',
            );

            $created = $this->rsync->syncWithConfirm($options, 'Lancer la création réelle ?');
        } finally {
            run("rm -rf " . escapeshellarg($buildDir));
        }

        if ($created ?? false) {
            io()->success('Environnement distant créé.');
            io()->note('Étapes suivantes :');
            io()->listing([
                'Importer la base de données WordPress.',
                'Envoyer les médias (castor media:push).',
                "Vérifier les permissions sur web/app/uploads/ et log/.",
                'Déployer l\'application (castor deploy).',
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function checkWordPressVersion(): void
    {
        $patch = $this->wp->getAvailablePatch();

        if ($patch === null) {
            return;
        }

        io()->warning(
            "Patch WordPress disponible : {$patch}.\n"
            . "Il est recommandé de mettre à jour avant de déployer.\n"
            . "Commande : castor wp:apply-available-patch"
        );

        if (io()->confirm('Annuler le déploiement ?', true)) {
            exit(0);
        }
    }

    private function extractArchive(string $gitRevision, string $directory): void
    {
        @mkdir($directory, 0755, true);
        $basename = basename($directory);
        $parent   = dirname($directory);
        run("git archive --format=tar --prefix={$basename}/ {$gitRevision} | (cd {$parent} && tar xf -)");
    }

    private function writeDeployState(string $directory, string $gitRevision): void
    {
        @mkdir($directory, 0755, true);

        $commit = run('git rev-parse --short ' . escapeshellarg($gitRevision), quiet: true, allowFailure: true);
        if ($commit->isSuccessful()) {
            file_put_contents("{$directory}/git_commit", trim($commit->getOutput()) . PHP_EOL);
        }

        $tag = $this->resolveGitTag($gitRevision);
        if ($tag !== null) {
            file_put_contents("{$directory}/git_tag", $tag . PHP_EOL);
        }

        file_put_contents("{$directory}/time", date('Y-m-d H:i:s') . PHP_EOL);
    }

    private function resolveGitTag(string $gitRevision): ?string
    {
        $isTag = run(
            'git show-ref --verify --quiet refs/tags/' . escapeshellarg($gitRevision),
            quiet: true,
            allowFailure: true
        )->isSuccessful();

        if ($isTag) { return $gitRevision; }
        if (str_contains($gitRevision, 'release_')) { return str_replace('release_', '', $gitRevision); }
        if (str_contains($gitRevision, 'hotfix_'))  { return str_replace('hotfix_', '', $gitRevision); }

        return null;
    }

    /** @param array<string, mixed> $config */
    private function buildRsyncOptions(string $buildDir, array $config, bool $ignoreAssets, bool $ignoreComposer): RsyncOptions
    {
        $excludes = [];
        if ($ignoreAssets)   { $excludes[] = 'web/app/themes/*/dist/'; }
        if ($ignoreComposer) { $excludes = array_merge($excludes, ['vendor/', 'web/wp/']); }

        return new RsyncOptions(
            fromHost:      null,
            fromUser:      null,
            fromPath:      $buildDir,
            toHost:        (string) $config['REMOTE_HOSTNAME'],
            toUser:        (string) $config['REMOTE_USERNAME'],
            toPath:        (string) $config['REMOTE_PATH'],
            port:          (int) ($config['REMOTE_PORT'] ?? 22),
            delete:        true,
            chmod:         'Du=rwx,Dgo=rx,Fu=rw,Fgo=r',
            excludeFrom:   "{$buildDir}/.rsyncignore",
            extraExcludes: $excludes,
        );
    }

    private function runPostDeployWebhooks(string $scheme, string $domain, string $path): void
    {
        $siteUrl = rtrim("{$scheme}://{$domain}{$path}", '/') . '/';

        $webhooks = [
            'Réinitialiser l\'opcache ?'          => 'reset-opcache',
            'Vider le statcache ?'                => 'clear-statcache',
            'Flusher les rewrite rules ?'         => 'flush-rewrite-rules',
            'Vider le cache wp-cubi transient ?'  => 'clear-wp-cubi-transient-cache',
        ];

        foreach ($webhooks as $question => $hook) {
            if (io()->confirm($question, true)) {
                $this->sendWebhook($siteUrl, $hook);
            }
        }

        run('curl -S -s -o /dev/null ' . escapeshellarg($siteUrl));
    }

    private function sendWebhook(string $siteUrl, string $webhook): void
    {
        $appConfig = "{$this->projectRoot}/config/application.php";
        if (!file_exists($appConfig)) { return; }

        $content = file_get_contents($appConfig);
        if (!preg_match("/define\('WP_CUBI_WEBHOOKS_SECRET',\s*'([^']+)'\)/", $content, $m)) { return; }

        $url = $siteUrl . '?wp-cubi-webhooks-run=' . urlencode($webhook) . '&wp-cubi-webhooks-secret=' . urlencode($m[1]);
        run('curl -S -s -o /dev/null ' . escapeshellarg($url));
    }
}
