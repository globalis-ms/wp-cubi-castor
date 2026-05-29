<?php

declare(strict_types=1);

namespace WpCubiCastor\WordPress;

use WpCubiCastor\Config\ConfigManager;
use WpCubiCastor\Infrastructure\WpCli;
use function Castor\io;
use function Castor\run;

/**
 * Manages all WordPress operations (installation, language, timezone, plugins, ACF…).
 */
final class WordPressManager
{
    private const SALT_KEYS_URL = 'https://api.wordpress.org/secret-key/1.1/salt/';

    public function __construct(
        private readonly WpCli         $wpCli,
        private readonly ConfigManager $config,
        private readonly string        $projectRoot,
    ) {}

    // -------------------------------------------------------------------------
    // Full installation
    // -------------------------------------------------------------------------

    /**
     * Full WordPress installation (called from the install task).
     */
    public function init(): void
    {
        io()->section('INSTALLATION WORDPRESS');

        $this->generateSaltKeys();
        $this->initConfigFile();
        $this->createDatabase();
        $this->installCore();

        if (io()->confirm('Installer ACF PRO ? (clé de licence requise)', false)) {
            $this->installAcfPro();
        }

        $this->installLanguage(null, activate: true);
        $this->updateTimezone();
        $this->clean();
        $this->activatePlugins();

        io()->success('WordPress est prêt.');
    }

    // -------------------------------------------------------------------------
    // Salt keys
    // -------------------------------------------------------------------------

    /**
     * Generates new salt keys from the official WordPress API.
     */
    public function generateSaltKeys(string $root = ''): void
    {
        $target = rtrim($root ?: $this->projectRoot, '/') . '/config/salt-keys.php';

        if (file_exists($target)) {
            if (!io()->confirm("{$target} existe déjà. Remplacer ?", false)) {
                return;
            }
            unlink($target);
        }

        $saltKeys = @file_get_contents(self::SALT_KEYS_URL);

        if (empty($saltKeys)) {
            throw new \RuntimeException('Impossible de récupérer les salt keys depuis ' . self::SALT_KEYS_URL);
        }

        @mkdir(dirname($target), 0755, true);
        file_put_contents($target, implode(PHP_EOL, [
            '<?php',
            '',
            '// WordPress salt keys generated from: ' . self::SALT_KEYS_URL,
            $saltKeys,
        ]));

        io()->success("Salt keys générées : {$target}");
    }

    // -------------------------------------------------------------------------
    // Language
    // -------------------------------------------------------------------------

    /**
     * Installs and activates a language.
     */
    public function installLanguage(?string $language = null, bool $activate = true): void
    {
        $language ??= io()->ask('Langue WordPress (ex: fr_FR)', 'fr_FR');
        $this->updateLanguageTranslations($language, $activate);
    }

    /**
     * Updates translations (core + plugins + themes).
     */
    public function updateLanguage(?string $language = null): void
    {
        if ($language === null) {
            $language = $this->wpCli->getOutput('option', 'get', 'WPLANG') ?: 'fr_FR';
        }

        $this->updateLanguageTranslations($language, activate: false);
    }

    // -------------------------------------------------------------------------
    // Timezone
    // -------------------------------------------------------------------------

    /**
     * Interactively configures the WordPress timezone.
     */
    public function updateTimezone(): void
    {
        $timezones = [];
        foreach (timezone_identifiers_list() as $tz) {
            $parts = explode('/', $tz, 2);
            $timezones[$parts[0]][$parts[1] ?? $parts[0]] = $tz;
        }

        $group = io()->choice('Région (1/2)', array_keys($timezones));
        $zone  = io()->choice('Fuseau (2/2)', array_keys($timezones[$group]));
        $value = $timezones[$group][$zone];

        $this->wpCli->run('option', 'update', 'timezone_string', $value);
        io()->success("Timezone définie : {$value}");
    }

    // -------------------------------------------------------------------------
    // ACF PRO
    // -------------------------------------------------------------------------

    /**
     * Installs ACF PRO via the private Composer repository.
     */
    public function installAcfPro(string $username = '', string $password = ''): void
    {
        io()->note(
            "Installation d'ACF PRO via connect.advancedcustomfields.com.\n"
            . "Username = clé de licence | Password = URL du site enregistré.\n"
            . "Voir : https://www.advancedcustomfields.com/resources/installing-acf-pro-with-composer/"
        );

        $username = $username ?: io()->ask('Username (clé de licence)');
        $password = $password ?: io()->ask('Password (URL du site)');

        run("composer remove wpackagist-plugin/advanced-custom-fields --working-dir={$this->projectRoot}");
        run("composer config http-basic.connect.advancedcustomfields.com {$username} {$password} --working-dir={$this->projectRoot}");
        run("composer config repositories.advancedcustomfields composer https://connect.advancedcustomfields.com --working-dir={$this->projectRoot}");
        run("composer require wpengine/advanced-custom-fields-pro --working-dir={$this->projectRoot}");

        io()->success('ACF PRO installé.');
    }

    // -------------------------------------------------------------------------
    // WordPress core updates
    // -------------------------------------------------------------------------

    /**
     * Returns the available patch version, or null if up to date.
     */
    public function getAvailablePatch(): ?string
    {
        $process = run(
            'composer outdated roots/wordpress --patch-only --strict --format=json',
            quiet: true,
            allowFailure: true
        );

        $data = json_decode($process->getOutput(), true);

        if (!is_array($data) || !isset($data['latest'], $data['versions'])) {
            return null;
        }

        $current = current($data['versions']);
        return version_compare($current, $data['latest'], '<') ? $data['latest'] : null;
    }

    /**
     * Applies the available patch update via Composer.
     */
    public function applyAvailablePatch(): void
    {
        $version = $this->getAvailablePatch();

        if ($version === null) {
            io()->info('Aucun patch disponible pour roots/wordpress.');
            return;
        }

        run("composer require roots/wordpress:~{$version} --with-all-dependencies --working-dir={$this->projectRoot}");
        run("composer bump roots/wordpress --working-dir={$this->projectRoot}");

        io()->success("WordPress mis à jour vers {$version}.");
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function initConfigFile(string $start = '<##', string $end = '##>'): void
    {
        $appConfig = "{$this->projectRoot}/config/application.php";
        if (!file_exists($appConfig)) {
            return;
        }

        $prefix  = io()->ask('Préfixe de table BDD', 'cubi_');
        $secret  = bin2hex(random_bytes(16));
        $content = file_get_contents($appConfig);

        $content = str_replace(
            ["{$start}DB_PREFIX{$end}", "{$start}WP_CUBI_WEBHOOKS_SECRET{$end}"],
            [$prefix, $secret],
            $content
        );

        file_put_contents($appConfig, $content);
    }

    private function createDatabase(): void
    {
        $dbName  = (string) $this->config->get('development', 'DB_NAME');
        $hasMysql = trim(run('command -v mysql 2>/dev/null', quiet: true, allowFailure: true)->getOutput()) !== '';

        if ($hasMysql) {
            $this->wpCli->run('db', 'create');
            io()->success("Base de données `{$dbName}` créée.");
        } else {
            io()->confirm("Binaire mysql introuvable. Créez la base `{$dbName}` manuellement puis appuyez Entrée.");
        }
    }

    private function installCore(): void
    {
        $title    = io()->ask('Titre du site');
        $username = io()->ask('Nom d\'utilisateur admin');
        $password = io()->askHidden('Mot de passe admin');
        $email    = io()->ask('Email admin', (string) $this->config->get('development', 'DEV_MAIL'));

        $this->wpCli->run(
            'core', 'install',
            '--title=' . $title,
            '--admin_user=' . $username,
            '--admin_password=' . $password,
            '--admin_email=' . $email,
            '--url=' . $this->resolveWpUrl(),
            '--skip-email',
        );

        io()->success('WordPress core installé.');
    }

    private function updateLanguageTranslations(string $language, bool $activate): void
    {
        // Core
        $this->wpCli->run('language', 'core', 'install', $language);
        if ($activate) {
            $this->wpCli->run('language', 'core', 'activate', $language);
        }
        $this->wpCli->run('language', 'core', 'update');

        // Plugins
        foreach (['active', 'inactive'] as $status) {
            $list = $this->wpCli->getOutput('plugin', 'list', '--field=name', "--status={$status}");
            foreach (array_filter(explode(PHP_EOL, $list)) as $plugin) {
                $this->wpCli->runSilent('language', 'plugin', 'install', $plugin, $language);
            }
        }
        $this->wpCli->run('language', 'plugin', 'update', '--all');

        // Themes
        $themes = $this->wpCli->getOutput('theme', 'list', '--field=name');
        foreach (array_filter(explode(PHP_EOL, $themes)) as $theme) {
            $this->wpCli->runSilent('language', 'theme', 'install', $theme, $language);
        }
        $this->wpCli->run('language', 'theme', 'update', '--all');

        io()->success("Traductions mises à jour : {$language}");
    }

    private function clean(): void
    {
        foreach (['web/app/uploads', 'web/app/upgrade'] as $dir) {
            $path = "{$this->projectRoot}/{$dir}";
            if (is_dir($path)) {
                run("rm -rf " . escapeshellarg($path));
            }
        }

        $this->wpCli->run('option', 'update', 'blogdescription', '');

        $ids = $this->wpCli->getOutput('post', 'list', '--post_type=any', '--format=ids');
        if (!empty($ids)) {
            $this->wpCli->run('post', 'delete', $ids, '--force', '--quiet');
        }

        foreach (['sidebars_widgets', 'widget_recent-posts', 'widget_recent-comments'] as $option) {
            $this->wpCli->run('option', 'update', $option, '{}', '--format=json', '--quiet');
        }
    }

    private function activatePlugins(): void
    {
        $this->wpCli->run('plugin', 'activate', '--all');
        $this->wpCli->run('cap', 'add', 'administrator', 'view_query_monitor');
        io()->success('Plugins activés.');
    }

    private function resolveWpUrl(): string
    {
        $scheme = (string) $this->config->get('development', 'WEB_SCHEME');
        $domain = (string) $this->config->get('development', 'WEB_DOMAIN');
        $path   = (string) $this->config->get('development', 'WEB_PATH');
        return "{$scheme}://{$domain}{$path}/wp";
    }
}
