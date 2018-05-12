<?php
/**
 * Compile Suffix Data
 *
 * @package blobfolio/domain
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

/**
 * Data Source: Public Suffix List
 *
 * @see {https://publicsuffix.org/list/public_suffix_list.dat}
 *
 * @copyright 2017 Mozilla Foundation.
 * @license https://www.mozilla.org/en-US/MPL/ MPL
 */

namespace blobfolio\dev;

use \blobfolio\common\mb as v_mb;
use \blobfolio\common\ref\sanitize as r_sanitize;
use \blobfolio\bob\utility;

class domains extends \blobfolio\bob\base\build {
	const NAME = 'domains';

	// Intl should catch this, but just in case...
	const REQUIRED_FUNCTIONS = array('idn_to_ascii');
	const DOWNLOADS = array('https://publicsuffix.org/list/public_suffix_list.dat');

	// We aren't using binaries or build steps.
	const SKIP_BINARY_DEPENDENCIES = true;
	const SKIP_BUILD = false;
	const SKIP_FILE_DEPENDENCIES = true;
	const SKIP_PACKAGE = true;

	// MaxMind URLs.
	const DATA_TEMPLATE = BOB_BUILD_DIR . 'skel/data.template';
	const DATA_SOURCE = 'https://publicsuffix.org/list/public_suffix_list.dat';
	const DATA_OUT = BOB_ROOT_DIR . 'lib/blobfolio/domain/data.php';



	// -----------------------------------------------------------------
	// Build
	// -----------------------------------------------------------------

	/**
	 * Build Tasks
	 *
	 * @return void Nothing.
	 */
	protected static function build_tasks() {
		utility::log('Loading data…');

		// Load the data.
		$data = file_get_contents(static::$downloads[static::DATA_SOURCE]);

		// We want to cut out the ICANN bits. If these strings don't
		// exist, there's something wrong.
		if (
			(false === ($start = v_mb::strpos($data, '// ===BEGIN ICANN DOMAINS==='))) ||
			(false === ($end = v_mb::strpos($data, '// ===END ICANN DOMAINS===')))
		) {
			utility::log('Unexpected data was returned.', 'error');
		}

		// Chop and sanitize.
		$data = v_mb::substr($data, $start, ($end - $start));
		r_sanitize::whitespace($data, 1);
		$data = explode("\n", $data);
		$data = array_filter($data, 'strlen');

		utility::log('Parsing data…');

		$suffixes = array();
		foreach ($data as $line) {
			// Skip comments.
			if (0 === strpos($line, '//')) {
				continue;
			}

			// Tease out the parts.
			$parts = preg_replace('/^!/', '!.', $line);
			$parts = explode('.', $parts);

			// Recurse.
			static::build_save($suffixes, $parts);
		}

		// Note how many we found.
		static::print_record_count(count($suffixes));

		utility::log('Exporting data…');

		$out = file_get_contents(static::DATA_TEMPLATE);
		$out = str_replace(
			array(
				'%GENERATED%',
				'%SUFFIXES%',
			),
			array(
				date('Y-m-d H:i:s'),
				utility::array_to_php($suffixes, 2),
			),
			$out
		);
		file_put_contents(static::DATA_OUT, $out);
	}

	/**
	 * Build Storage
	 *
	 * @param array $old Old.
	 * @param array $new New.
	 * @return void Nothing.
	 */
	protected static function build_save(&$old, $new) {
		// Ignore bad data.
		if (!is_array($old) || !is_array($new)) {
			return;
		}

		// Pop off the end.
		$part = array_pop($new);
		$part = idn_to_ascii($part);

		// If it doesn't exist, it's a new base.
		if (!isset($old[$part])) {
			$old[$part] = array();
		}

		// If there are other parts, do it all over again.
		if (count($new) > 0) {
			static::build_save($old[$part], $new);
		}
	}

	// ----------------------------------------------------------------- end build



	// -----------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------

	/**
	 * Record Count
	 *
	 * @param int $count Count.
	 * @return void Nothing.
	 */
	protected static function print_record_count(int $count) {
		$count = number_format($count, 0, '.', ',');
		utility::log("Total TLDs: $count", 'success');
	}

	// ----------------------------------------------------------------- end helpers
}
