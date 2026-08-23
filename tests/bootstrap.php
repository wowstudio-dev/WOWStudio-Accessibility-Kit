<?php
/**
 * PHPUnit bootstrap.
 *
 * These are true unit tests: WordPress is not loaded, its functions are mocked
 * with Brain Monkey. Integration tests against a real WordPress run under
 * wp-env and are added alongside the database layer.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/stubs.php';
