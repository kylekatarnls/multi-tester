# multi-tester

[![Latest Stable Version](https://poser.pugx.org/kylekatarnls/multi-tester/v/stable.png)](https://packagist.org/packages/kylekatarnls/multi-tester)
[![License](https://poser.pugx.org/kylekatarnls/multi-tester/license)](https://packagist.org/packages/kylekatarnls/multi-tester)
[![Build Status](https://travis-ci.org/kylekatarnls/multi-tester.svg?branch=master)](https://travis-ci.org/kylekatarnls/multi-tester)
[![StyleCI](https://styleci.io/repos/168829625/shield?style=flat)](https://styleci.io/repos/168829625)
[![Test Coverage](https://codeclimate.com/github/kylekatarnls/multi-tester/badges/coverage.svg)](https://codecov.io/github/kylekatarnls/multi-tester?branch=master)
[![Code Climate](https://codeclimate.com/github/kylekatarnls/multi-tester/badges/gpa.svg)](https://codeclimate.com/github/kylekatarnls/multi-tester)
[![Dependencies](https://tidelift.com/badges/github/kylekatarnls/multi-tester)](https://tidelift.com/subscription/pkg/packagist-pug-php-pug?utm_source=packagist-pug-php-pug&utm_medium=referral&utm_campaign=readme)

When you get multiple projects with strong dependencies between themselves or a project that many other depends on
and make a change on one of them, you not only want this project's unit tests to pass, but all other to still pass
considering this change. Even with a full coverage of each project, it's not rare to get a project broken by a very
small change in one of its dependencies despite that change seemed pretty harmless.

If you package manager is **composer**, here comes **multi-tester** to the rescue. It will allow you to run unit tests
of other project(s) replacing your package in their vendor directory with the current state of your package.

**multi-tester** is **Travis CI** friendly. Packages with `.travis.yml` will automatically be handled using **Travis CI**
standard commands.

## Installation

You first need to use [composer](https://getcomposer.org) for your project and have a **composer.json** file at the
root of your project with a **"name"** property defined (it will be used to replace the code of your project from the
**vendor** directory of other projects with the current changes).

Then you need to install multi-tester as a development dependency:
```
composer require kylekatarnls/multi-tester --save-dev
```

## Use

Once installed, the local command `vendor/bin/multi-tester` will be available. Without argument, it will try to load
its configuration from **.multi-tester.yml** file in the current directory. But you can specify an other location
as the first argument: `vendor/bin/multi-tester ./directory/config.json` (config file can be a `.json` or a `.yml`).

You also can get detailed output with `-v` or `--verbose` flag.

## Configuration

The **.multi-tester.yml** config file is where you will list projects and how to download, install and test them.

```yaml
config: # config entry is optional, it's about main config
  # By default, multi-tester assumes composer.json is in the same directory than .multi-tester.yml
  # But you can customize it to a relative path:
  directory: ../foobar

# Specify a vendor/package name as entry
symfony/symfony:
  # Specify how to download the project:
  clone: git clone https://github.com/symfony/symfony.git .
  # Specify how to install dependencies of the project:
  install: composer install
  # Specify how to run unit tests of the project:
  script: vendor/bin/phpunit

my-org/an-other-project: ...
```

All entry of a project configuration are optionals.

If you don't specify `clone`, **multi-tester** will check the package name at packagist.org (composer registry) and
get the Git url from it (other VCS are not supported yet). Instead of `clone`, you can also specify a `version` entry
to filter packages versions (using packagist.org API). Without version, the last stable will be used.


```yaml
symfony/symfony:
  version: ^3.2 # can be any semver string: >4.5, ~3.1.0, etc.

# You can pass the version string directly in the package name, so you can run the same package at different versions
symfony/symfony:5.4.*: default # 'default' means all settings use the default one
```

If you don't specify `install`, `composer install --no-interaction` will be used by default.

If you don't specify `script`, `vendor/bin/phpunit --no-coverage` will be used by default.

If you set `install: travis`, **multi-tester** will copy the `install` command from the **.travis.yml** file of
the package you test.

If you set `script: travis`, **multi-tester** will copy the `script` command from the **.travis.yml** file of
the package you test.

To get both from **.travis.yml**, use the shortcut:

```yaml
symfony/symfony:5.4.*: travis
```

## Travis

To not have to check manually with multi-tester, you should have it in your CI (continuous integration) process.
For example if you use Travis, here is how to integrate in it and then get every project tested at each commit.

Let's say you have the following **.travis.yml**:

```yaml
language: php

php:
  - 7.1
  - 7.2
  - 7.3

install:
  - composer install

script:
  - vendor/bin/phpunit
```

Then you can add a line for **multi-tester** to your builds with:

```yaml
language: php

matrix:
  include:
    - php: 7.1
    - php: 7.2
    - php: 7.3
    - php: 7.3
      env: MULTITEST='on'

install:
  - composer install

script:
  - if [ "$MULTITEST" != "on" ]; then vendor/bin/phpunit; fi;
  - if [ "$MULTITEST" = "on" ]; then vendor/bin/multi-tester; fi;
```
