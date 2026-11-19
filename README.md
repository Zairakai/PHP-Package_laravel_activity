<p align="center">
  <img src="https://gitlab.com/zairakai/php-packages/laravel-activity/-/raw/main/assets/icon.png" alt="Laravel Activity" width="160">
</p>

# zairakai/laravel-activity

[![Main][pipeline-main-badge]][pipeline-main-link]
[![Develop][pipeline-develop-badge]][pipeline-develop-link]
[![Coverage][coverage-badge]][coverage-link]

[![GitLab Release][gitlab-release-badge]][gitlab-release]
[![Packagist][packagist-badge]][packagist]
[![Docs][docs-badge]][docs]
[![Downloads][downloads-badge]][packagist]
[![License][license-badge]][license]

[![PHP][php-badge]][php]
[![Laravel][laravel-badge]][laravel]
[![Static Analysis][phpstan-badge]][phpstan]
[![Code Style][pint-badge]][pint]

Activity logs for the changes of Eloquent many-to-many relationships, built on [Spatie Laravel Activity Log](https://github.com/spatie/laravel-activitylog).

**Documentation: [laravel-activity-a54436.gitlab.io][docs]**

---

## Features

- **History of the pivot table**: a pivot table has no model, so each entry keeps its rows before and after the change.
- **Fluent API**: `->activity()` on a `BelongsToMany` or `MorphToMany` relation, then `attach`, `detach`, `sync`, `syncWithoutDetaching`, `toggle` or `updateExistingPivot`.
- **Built on Spatie**: same causer, log name and enable switch, with the data in `attribute_changes`.
- **Safe**: sensitive columns (`password`, `token`…) are never logged, and nothing is logged when nothing changes.
- **`TraceActivities` trait**: the entries a model caused, the ones on it, and the ones where it is on the other side of a relation.

## Requirements

PHP 8.4 or higher, Laravel 12 or 13, and `spatie/laravel-activitylog` 5 (installed with the package, with its `activity_log` table migrated: Spatie ships no migration for a table of an older version, see [its upgrade guide][activitylog-upgrading]).

## Install

```bash
composer require zairakai/laravel-activity
php artisan vendor:publish --tag=laravel-activity-config   # optional
```

## Configuration

The package has one optional config file, `config/laravel-activity.php`.

| Key | Role |
| --- | --- |
| `log_name` | log of the entries (`pivot` by default, `ACTIVITY_PIVOT_LOG_NAME`), `null` for the default log of Spatie |
| `hidden_columns` | columns of the pivot tables never written in the log (`password`, `remember_token`, `token`, `api_token`, `secret` by default) |
| `use_pivot_hidden` | `true` by default (`ACTIVITY_PIVOT_USE_HIDDEN`): also leave out the `$hidden` of the pivot model (`->using(RoleUser::class)`) |

## Usage

```php
$user->roles()
    ->activity()
    ->by(auth()->user())
    ->withMessage('Assigned roles')
    ->sync([1, 2, 3]);

$user->tags()->activity()->attach(5);
$user->tags()->activity()->detach(5);
```

<details>
<summary>What an entry contains</summary>

One entry per operation. The subject is the parent model, the `event` is the operation (`attached`, `detached`, `synced`, `synced_without_detaching`, `toggled`, `updated_pivot`), the `properties` hold the relation, the pivot table and the type of the related model, and `attribute_changes` hold the pivot rows before (`old`) and after (`attributes`), by ID of the related model, only for the rows that changed. Detaching the role 3 from the user 1:

```json
{
    "event": "detached",
    "log_name": "pivot",
    "description": "Detached 1 role",
    "properties": {
        "relation": "roles",
        "table": "role_user",
        "related_type": "App\\Models\\Role"
    },
    "attribute_changes": {
        "old": { "3": { "user_id": 1, "role_id": 3, "granted_by": "System" } },
        "attributes": []
    }
}
```

An attached row is only in `attributes`, a detached one only in `old` (with all its columns, so it can be restored), a modified one in both.

</details>

<details>
<summary>Causer, message, log and columns</summary>

- `->by($model)`, `->by($id)` and `->byAnonymous()` work as in Spatie (`causedBy()` and `causedByAnonymous()` too). Without a causer, Spatie decides.
- `->withMessage('…')` is for the next operation only.
- `->inLog('audit')` writes the entries of the instance in another log.
- `->withoutColumns(['granted_by'])` leaves more columns out of the rows.
- When Spatie is disabled, the operation runs and nothing is written.

</details>

<details>
<summary>Query the entries of a model</summary>

```php
use Zairakai\LaravelActivity\Traits\TraceActivities;

$user->activities()->on()->get();       // its relations changed
$user->activities()->by()->get();       // caused by the user
$user->activities()->all();             // both
$role->activities()->related()->get();  // where the role was attached or detached, on any user
```

`related()` filters on the `log_name` (indexed) before it reads the JSON, so it does not find the entries written with `->inLog()` in another log. On a big table, an index on the JSON paths of your database helps.

</details>

## Development

See [CONTRIBUTING.md][contributing] for the workflow and the quality standards. `composer install` and `make install-hooks` set the package up.

```bash
make quality        # Pint, PHPStan and Markdownlint (with the rest of the gate)
make quality-fast   # Pint, PHPStan and Markdownlint
make quality-fix    # every automatic fix
make test           # PHPUnit
make bats           # the structure tests of the repository
make ci             # the whole gate: quality, tests and Bats
```

The configuration of each tool is in `config/dev-tools/`, one file per setting. These files come from [laravel-dev-tools][dev-tools]: `make setup-check` tells which ones are late on the package (`make setup-update` publishes them again).

## Statistics

![Statistics of laravel-activity][stats-card]

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md][contributing] for the project-specific workflow and quality standards.

---

## Getting Help

[![License][license-badge]][license]
[![Security Policy][security-badge]][security]
[![Issues][issues-badge]][issues]

**Made with ❤️ by [Zairakai][ecosystem]**

<!-- Reference Links -->
[pipeline-main-badge]: https://gitlab.com/zairakai/php-packages/laravel-activity/badges/main/pipeline.svg?ignore_skipped=true&key_text=Main
[pipeline-main-link]: https://gitlab.com/zairakai/php-packages/laravel-activity/commits/main
[pipeline-develop-badge]: https://gitlab.com/zairakai/php-packages/laravel-activity/badges/develop/pipeline.svg?ignore_skipped=true&key_text=Develop
[pipeline-develop-link]: https://gitlab.com/zairakai/php-packages/laravel-activity/commits/develop
[coverage-badge]: https://gitlab.com/zairakai/php-packages/laravel-activity/badges/main/coverage.svg
[coverage-link]: https://gitlab.com/zairakai/php-packages/laravel-activity/-/commits/main
[gitlab-release-badge]: https://img.shields.io/gitlab/v/release/zairakai/php-packages/laravel-activity?logo=gitlab
[gitlab-release]: https://gitlab.com/zairakai/php-packages/laravel-activity/-/releases
[packagist-badge]: https://img.shields.io/packagist/v/zairakai/laravel-activity
[packagist]: https://packagist.org/packages/zairakai/laravel-activity
[downloads-badge]: https://img.shields.io/packagist/dt/zairakai/laravel-activity
[license-badge]: https://img.shields.io/badge/license-MIT-blue.svg
[license]: ./LICENSE
[security-badge]: https://img.shields.io/badge/security-scanned-green.svg
[security]: ./SECURITY.md
[issues-badge]: https://img.shields.io/gitlab/issues/open-raw/zairakai%2Fphp-packages%2Flaravel-activity?logo=gitlab&label=Issues
[issues]: https://gitlab.com/zairakai/php-packages/laravel-activity/-/issues
[php-badge]: https://img.shields.io/badge/php-8.4-blue?logo=php
[php]: https://www.php.net
[laravel-badge]: https://img.shields.io/badge/Laravel-12%20%7C%2013-red?logo=laravel
[laravel]: https://laravel.com
[phpstan-badge]: https://img.shields.io/badge/static%20analysis-phpstan-5B2C6F.svg?logo=php
[phpstan]: https://phpstan.org
[pint-badge]: https://img.shields.io/badge/code%20style-pint-22C55E.svg
[pint]: https://laravel.com/docs/pint
[ecosystem]: https://gitlab.com/zairakai
[contributing]: ./CONTRIBUTING.md
[dev-tools]: https://gitlab.com/zairakai/php-packages/laravel-dev-tools
[activitylog-upgrading]: https://github.com/spatie/laravel-activitylog/blob/main/UPGRADING.md
[docs]: https://laravel-activity-a54436.gitlab.io
[docs-badge]: https://img.shields.io/badge/docs-online-blue
[stats-card]: https://gitlab.com/zairakai/gitlab-profile/-/raw/main/assets/projects/laravel-activity.svg
