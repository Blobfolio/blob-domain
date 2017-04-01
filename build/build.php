<?php
/**
 * Compile suffix source data.
 *
 * This build script will download Public Suffix List
 * data and reformat it in a way that is friendlier
 * to PHP.
 *
 * This script should be run via php-cli.
 *
 * Requires:
 * PHP 7+
 * UNIX
 * CURL
 * MBSTRING
 * DOM
 *
 * @see {https://publicsuffix.org/list/public_suffix_list.dat}
 *
 * @package blobfolio/domain
 * @author	Blobfolio, LLC <hello@blobfolio.com>
 */

// -------------------------------------------------
// Setup/Env

$start = microtime(true);

define('BUILD_PATH', dirname(__FILE__));
define('SOURCE_PATH', BUILD_PATH . '/src');
define('DIST_TEMPLATE', BUILD_PATH . '/data.template');
define('DIST_PATH', dirname(BUILD_PATH) . '/lib/blobfolio/domain/data.php');

// Load the bootstrap.
@require_once(dirname(dirname(__FILE__)) . '/lib/vendor/autoload.php');

define('PUBLIC_SUFFIX_LIST', 'https://publicsuffix.org/list/public_suffix_list.dat');

// How long should downloaded files be cached?
define('DOWNLOAD_CACHE', 7200);



/**
 * STDOUT wrapper.
 *
 * Make it easier to print progress to the terminal.
 *
 * @param string $line Content.
 * @param bool $dividers Print dividing lines.
 * @return void Nothing.
 */
function debug_stdout(string $line='', bool $dividers=false) {
	if ($dividers) {
		echo str_repeat('-', 50) . "\n";
	}
	echo "$line\n";
	if ($dividers) {
		echo str_repeat('-', 50) . "\n";
	}
}



/**
 * URL to Cache Path
 *
 * The local name to use for a given URL.
 *
 * @param string $url URL.
 * @return string Path.
 */
function cache_path(string $url) {
	// Strip and translate a little.
	$url = strtolower($url);
	$url = preg_replace('/^https?:\/\//', '', $url);
	$url = str_replace(array('/','\\','?','#'), '-', $url);

	return SOURCE_PATH . '/' . $url;
}



/**
 * Get Cache
 *
 * Return the local content if available.
 *
 * @param string $url URL.
 * @return string|bool Content or false.
 */
function get_cache(string $url) {
	static $limit;

	// Set the limit if we haven't already.
	if (is_null($limit)) {
		file_put_contents(SOURCE_PATH . '/limit', 'hi');
		$limit = filemtime(SOURCE_PATH . '/limit') - DOWNLOAD_CACHE;
		unlink(SOURCE_PATH . '/limit');
	}

	try {
		$file = cache_path($url);
		if (file_exists($file)) {
			if (filemtime($file) < $limit) {
				unlink($file);
			}
			else {
				return file_get_contents($file);
			}
		}
	} catch (Throwable $e) {
		return false;
	}

	return false;
}



/**
 * Save Cache
 *
 * Save a fetched URL locally.
 *
 * @param string $url URL.
 * @param string $content Content.
 * @return bool True/false.
 */
function save_cache(string $url, string $content) {
	try {
		$file = cache_path($url);
		return @file_put_contents($file, $content);
	} catch (Throwable $e) {
		return false;
	}

	return false;
}



/**
 * Batch CURL URLs
 *
 * It is much more efficient to use multi-proc
 * CURL as there are hundreds of files to get.
 *
 * @param array $urls URLs.
 * @return array Responses.
 */
function fetch_urls(array $urls=array()) {
	$fetched = array();
	$cached = array();

	// Bad start...
	if (!count($urls)) {
		return $fetched;
	}

	// Loosely filter URLs, and look for cache.
	foreach ($urls as $k=>$v) {
		$urls[$k] = filter_var($v, FILTER_SANITIZE_URL);
		if (!preg_match('/^https?:\/\//', $urls[$k])) {
			unset($urls[$k]);
			continue;
		}

		if (false !== $cache = get_cache($urls[$k])) {
			$cached[$urls[$k]] = $cache;
			unset($urls[$k]);
			continue;
		}
	}

	$urls = array_chunk($urls, 25);

	foreach ($urls as $chunk) {
		$multi = curl_multi_init();
		$curls = array();

		// Set up curl request for each site.
		foreach ($chunk as $url) {
			$curls[$url] = curl_init($url);

			curl_setopt($curls[$url], CURLOPT_HEADER, false);
			curl_setopt($curls[$url], CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curls[$url], CURLOPT_TIMEOUT, 10);
			curl_setopt($curls[$url], CURLOPT_USERAGENT, 'blob-domain');
			curl_setopt($curls[$url], CURLOPT_FOLLOWLOCATION, true);

			curl_multi_add_handle($multi, $curls[$url]);
		}

		// Process requests.
		do {
			curl_multi_exec($multi, $running);
			curl_multi_select($multi);
		} while ($running > 0);

		// Update information.
		foreach ($chunk as $url) {
			$fetched[$url] = (int) curl_getinfo($curls[$url], CURLINFO_HTTP_CODE);
			if ($fetched[$url] >= 200 && $fetched[$url] < 400) {
				$fetched[$url] = curl_multi_getcontent($curls[$url]);
				save_cache($url, $fetched[$url]);
			}
			curl_multi_remove_handle($multi, $curls[$url]);
		}

		curl_multi_close($multi);
	}

	// Add our cached results back.
	foreach ($cached as $k=>$v) {
		$fetched[$k] = $v;
	}

	return $fetched;
}



/**
 * Array to PHP Code
 *
 * Convert a variable into a string
 * representing PHP code.
 *
 * @param array $var Data.
 * @param int $indents Number of tabs to append.
 * @return string Code.
 */
function array_to_php($var, int $indents=1) {
	if (!is_array($var) || !count($var)) {
		return '';
	}

	$out = array();
	$array_type = \blobfolio\common\cast::array_type($var);
	foreach ($var as $k=>$v) {
		$line = str_repeat("\t", $indents);
		if ('sequential' !== $array_type) {
			if (is_numeric($k)) {
				$line .= "$k=>";
			}
			else {
				$line .= "'$k'=>";
			}
		}
		if (is_array($v)) {
			$line .= 'array(' . array_to_php($v, $indents + 1) . ')';
		}
		elseif (is_numeric($v)) {
			$line .= $v;
		}
		else {
			$line .= "'$v'";
		}
		$out[] = $line;
	}

	return "\n" . implode(",\n", $out) . "\n" . str_repeat("\t", $indents - 1);
}



/**
 * Save Data
 *
 * @param array $old Old.
 * @param array $new New.
 * @return bool True/false.
 */
function save_data(&$old, $new) {
	if (!is_array($old) || !is_array($new)) {
		return false;
	}

	$part = array_pop($new);
	$part = idn_to_ascii($part);

	if (!isset($old[$part])) {
		$old[$part] = array();
	}

	if (count($new) > 0) {
		save_data($old[$part], $new);
	}

	return true;
}



// -------------------------------------------------
// Begin!

if (!file_exists(SOURCE_PATH)) {
	mkdir(SOURCE_PATH, 0755);
}



// -------------------------------------------------
// Fetch!

debug_stdout('PUBLIC SUFFIX LIST', true);
debug_stdout('   ++ Fetching source data...');
$data = fetch_urls(array(PUBLIC_SUFFIX_LIST));
if (is_int($data[PUBLIC_SUFFIX_LIST])) {
	debug_stdout('   ++ Fetch FAILED...');
	exit;
}
else {
	// We are only interested in the ICANN portions.
	$data = $data[PUBLIC_SUFFIX_LIST];
	if (
		false !== ($s = mb_strpos($data, '// ===BEGIN ICANN DOMAINS===', 0, 'UTF-8')) &&
		false !== ($e = mb_strpos($data, '// ===END ICANN DOMAINS===', 0, 'UTF-8'))
	) {
		$data = mb_substr($data, $s, ($e - $s), 'UTF-8');
		\blobfolio\common\ref\sanitize::whitespace($data, 1);
		$data = explode("\n", $data);
		$data = array_filter($data, 'strlen');
	}
	else {
		debug_stdout('   ++ Fetch FAILED...');
		@unlink(cache_path(PUBLIC_SUFFIX_LIST));
		exit;
	}
}



// -------------------------------------------------
// Parse!

debug_stdout('   ++ Parsing data...');
$suffixes = array();
foreach ($data as $line) {
	// Skip comments.
	if ('//' === mb_substr($line, 0, 2, 'UTF-8')) {
		continue;
	}

	$parts = preg_replace('/^!/', '!.', $line);
	$parts = explode('.', $parts);

	save_data($suffixes, $parts);
}



// -------------------------------------------------
// Save!

debug_stdout('   ++ Saving data...');
$replacements = array(
	'%GENERATED%'=>date('Y-m-d H:i:s'),
	'%SUFFIXES%'=>array_to_php($suffixes, 2)
);
$out = file_get_contents(DIST_TEMPLATE);
$out = str_replace(array_keys($replacements), array_values($replacements), $out);
@file_put_contents(DIST_PATH, $out);



$end = microtime(true);
debug_stdout('');
debug_stdout('Done!', true);
debug_stdout('   ++ Finished in ' . round($end - $start, 2) . ' seconds.');
