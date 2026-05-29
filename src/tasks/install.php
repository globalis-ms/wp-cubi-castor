<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;
use function Castor\io;

// ---------------------------------------------------------------------------
// install
// ---------------------------------------------------------------------------

#[AsTask(
    name: 'install',
    description: 'Installation complète du projet (build + optionnellement WordPress + git init)',
)]
function install_project(bool $setupWordpress = false, string $themeSlug = ''): void
{
    build_all(themeSlug: $themeSlug);

    $saltFile = wcc_root() . '/config/salt-keys.php';

    if ($setupWordpress) {
        wcc_wp()->init();
    } elseif (!file_exists($saltFile)) {
        wcc_wp()->generateSaltKeys();
    }

    if (!is_dir(wcc_root() . '/.git/')) {
        if (io()->confirm('Initialiser un dépôt Git ?', true)) {
            $message = io()->ask('Message du commit initial', 'Initial commit');
            wcc_git_flow()->init(wcc_root(), $message);
        }
    }

    io()->success('Installation terminée. Admin WordPress : ' . wcc_root());
}
