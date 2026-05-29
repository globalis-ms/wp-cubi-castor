> [!WARNING]
> **Work In Progress** — This project is under active development. The public API may change without notice.

# wp-cubi-castor

Object-oriented [Castor](https://castor.jolicode.com/) task runner for WordPress globalis/wp-cubi projects at Globalis.

Replaces `wp-cubi-robo` and `wp-cubi-robo-globalis`.

---

## Requirements

- PHP >= 8.1
- [Castor](https://castor.jolicode.com/) installed globally
- `rsync`, `git` available in PATH
- WP-CLI (for `wp:*` tasks)

## Installation

In your WordPress project's `composer.json`:

```json
{
    "require": {
        "globalis/wp-cubi-castor": "dev-main"
    }
}
```

Then in your root `castor.php`:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/globalis/wp-cubi-castor/src/bootstrap.php';

use function Castor\import;

import(__DIR__ . '/vendor/globalis/wp-cubi-castor/src/tasks');
```

## Available tasks

### Installation & configuration

| Command | Description |
|---|---|
| `castor install` | Full project installation (build + WordPress + git init) |
| `castor configure` | Interactively configure environment variables |

### Build

| Command | Description |
|---|---|
| `castor build:all` | Full build: Composer, config, htaccess and assets |
| `castor build:composer` | Install Composer dependencies |
| `castor build:config` | Generate configuration files |
| `castor build:htaccess` | Assemble `.htaccess` from config fragments |
| `castor build:assets` | Compile SCSS, JS, images and fonts for the theme |

### Theme

| Command | Description |
|---|---|
| `castor theme:watch` | Watch source files and recompile on change |

### Deployment

| Command | Description |
|---|---|
| `castor deploy <env> <revision>` | Deploy a Git revision to a remote environment via rsync |
| `castor deploy:setup <env>` | Initialize the remote environment structure (first time) |
| `castor media:dump <env>` | Pull media files from the remote server to local |
| `castor media:push <env>` | Push local media files to the remote server |

### WordPress

| Command | Description |
|---|---|
| `castor wp:generate-salt-keys` | Generate new WordPress salt keys from the official API |
| `castor wp:language:install` | Install and activate a language (core + plugins + themes) |
| `castor wp:language:update` | Update WordPress translations |
| `castor wp:update-timezone` | Interactively configure the WordPress timezone |
| `castor wp:install-acf-pro` | Install ACF PRO via the private Composer repository |
| `castor wp:show-available-patch` | Show whether a WordPress core patch update is available |
| `castor wp:apply-available-patch` | Apply the latest WordPress core patch update |

### Git Flow

| Command | Description |
|---|---|
| `castor feature:start <name>` | Start a feature branch |
| `castor feature:finish <name>` | Finish a feature branch |
| `castor hotfix:start [version]` | Start a hotfix (bumps patch by default) |
| `castor hotfix:finish [version]` | Finish a hotfix and create the version tag |
| `castor release:start [version]` | Start a release (bumps minor by default) |
| `castor release:finish [version]` | Finish a release and create the version tag |

## Project structure

```
src/
├── bootstrap.php          # Shared factories (lazy singletons)
├── Build/
│   ├── AssetsBuilder.php  # SCSS/JS/images/fonts compilation
│   ├── AssetsOptions.php  # Asset build options
│   └── Builder.php        # Full build (composer, config, htaccess, assets)
├── Config/
│   └── ConfigManager.php  # Environment variable management
├── Deploy/
│   ├── Deployer.php       # rsync deployment
│   └── MediaSync.php      # Media file synchronization
├── Git/
│   ├── GitFlowManager.php # Git flow management
│   └── SemanticVersion.php # Semantic versioning from git tags
├── Infrastructure/
│   ├── RsyncOptions.php
│   ├── RsyncRunner.php
│   └── WpCli.php
├── WordPress/
│   └── WordPressManager.php
└── tasks/                 # Castor tasks (functions exposed as CLI commands)
    ├── assets.php
    ├── build.php
    ├── deploy.php
    ├── git.php
    ├── install.php
    └── wordpress.php
```

## License

GPL-3.0-or-later — see [LICENSE](LICENSE).
