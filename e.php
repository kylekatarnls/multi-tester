<?php

include_once __DIR__ . '/vendor/autoload.php';

$file = new \MultiTester\File(__DIR__ . '/tests/bad-name/.multi-tester.yml');

var_dump($file->toArray());
