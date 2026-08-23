<?php
/**
 * Uninstall routine.
 *
 * Runs only when the site owner deletes the plugin, and only removes data when
 * they have explicitly opted in. Everything here is destructive by definition,
 * so the default is to keep the data.
 *
 * @package WOWStudio\AccessibilityKit
 */

defined( 'ABSPATH' ) || exit;
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/src/Uninstaller.php';

WOWStudio\AccessibilityKit\Uninstaller::run();
