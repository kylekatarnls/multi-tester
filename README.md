# multi-tester

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
