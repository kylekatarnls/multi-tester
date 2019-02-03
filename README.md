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

## Configuration


vendor/bin/phpunit
