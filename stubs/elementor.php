<?php
/**
 * Minimal Elementor declarations, for static analysis only.
 *
 * Elementor is not a dependency of this plugin and is never installed alongside
 * it — it is one of the things this plugin may find itself running next to.
 * These declarations exist so PHPStan can check the one place that reaches into
 * it, and every one of those reaches is guarded at runtime precisely because
 * none of this is guaranteed to be there. Never loaded at runtime, and excluded
 * from the shipped build.
 *
 * @package WOWStudio\AccessibilityKit
 */

// phpcs:disable

namespace Elementor {

	class Plugin {
		/**
		 * @var Plugin|null
		 */
		public static $instance;

		/**
		 * Nullable on purpose. Elementor builds these during its own boot, so
		 * on an early hook they are genuinely absent — which is why the calling
		 * code checks, and why declaring them non-nullable here would make
		 * PHPStan call a necessary guard redundant.
		 *
		 * @var \Elementor\Core\Documents_Manager|null
		 */
		public $documents;

		/**
		 * @var \Elementor\Frontend|null
		 */
		public $frontend;
	}

	class Frontend {
		/**
		 * @param int  $post_id
		 * @param bool $with_css
		 * @return string
		 */
		public function get_builder_content_for_display( $post_id, $with_css = false ) { return ''; }
	}
}

namespace Elementor\Core {

	class Documents_Manager {
		/**
		 * @param int $post_id
		 * @return \Elementor\Core\Base\Document|false
		 */
		public function get( $post_id ) { return false; }
	}
}

namespace Elementor\Core\Base {

	class Document {
		/**
		 * @return bool
		 */
		public function is_built_with_elementor() { return false; }
	}
}
