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


use \blobfolio\dev\domains;

require(__DIR__ . '/lib/vendor/autoload.php');

// Set up some quick constants, namely for path awareness.
define('BOB_BUILD_DIR', __DIR__ . '/');
define('BOB_ROOT_DIR', dirname(BOB_BUILD_DIR) . '/');

// Compilation is as easy as calling this method!
domains::compile();

// We're done!
exit(0);
