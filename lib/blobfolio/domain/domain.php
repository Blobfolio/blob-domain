<?php
/**
 * Domain
 *
 * This is the main domain class.
 *
 * @package blobfolio/domain
 * @author	Blobfolio, LLC <hello@blobfolio.com>
 */

namespace blobfolio\domain;

use \blobfolio\common;

class domain {

	const HOST_PARTS = array(
		'host'=>null,
		'subdomain'=>null,
		'domain'=>null,
		'suffix'=>null
	);

	protected $host;
	protected $subdomain;
	protected $domain;
	protected $suffix;

	protected $dns;



	// ---------------------------------------------------------------------
	// Init
	// ---------------------------------------------------------------------

	/**
	 * Construct
	 *
	 * @param string $host Host.
	 * @param bool $www Strip leading www.
	 * @return bool True/false.
	 */
	public function __construct($host='', $www=false) {
		common\ref\cast::to_bool($www, true);

		// Parse the parts.
		if (false === ($parsed = static::parse_host_parts($host))) {
			return false;
		}

		$this->host = $parsed['host'];
		$this->subdomain = $parsed['subdomain'];
		$this->domain = $parsed['domain'];
		$this->suffix = $parsed['suffix'];

		if ($www) {
			$this->strip_www();
		}

		return true;
	}

	/**
	 * Parse Host
	 *
	 * Try to tease the hostname out of any arbitrary
	 * string, which might be the hostname, a URL, or
	 * something else.
	 *
	 * @param string $host Host.
	 * @return string|bool Host or false.
	 */
	public static function parse_host($host) {
		// Try to parse it the easy way.
		$tmp = common\mb::parse_url($host, PHP_URL_HOST);
		if ($tmp) {
			$host = $tmp;
		}
		// Or the hard way?
		else {
			common\ref\cast::to_string($host, true);
			common\ref\mb::trim($host);

			// Cut off the path, if any.
			if (false !== ($start = common\mb::strpos($host, '/'))) {
				$host = common\mb::substr($host, 0, $start);
			}

			// Cut off the query, if any.
			if (false !== ($start = common\mb::strpos($host, '?'))) {
				$host = common\mb::substr($host, 0, $start);
			}

			// Cut off credentials, if any.
			if (false !== ($start = common\mb::strpos($host, '@'))) {
				$host = common\mb::substr($host, $start + 1);
			}

			// Is this an IPv6 address?
			if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
				common\ref\sanitize::ip($host, true);
			}
			elseif (
				0 === common\mb::strpos($host, '[') &&
				false !== ($end = common\mb::strpos($host, ']'))
			) {
				$host = common\mb::substr($host, 1, $end - 1);
				common\ref\sanitize::ip($host, true);
			}
			// Cut off port, if any.
			elseif (false !== ($start = common\mb::strpos($host, ':'))) {
				$host = common\mb::substr($host, 0, $start);
			}

			// If it is empty or invalid, there is nothing we can do.
			if (!strlen($host)) {
				return false;
			}

			// Convert to ASCII if possible.
			if (function_exists('idn_to_ascii')) {
				$host = explode('.', $host);
				$host = array_map('idn_to_ascii', $host);
				$host = implode('.', $host);
			}

			// Lowercase it.
			common\ref\mb::strtolower($host);

			// Get rid of trailing periods.
			$host = ltrim($host, '.');
			$host = rtrim($host, '.');
		}

		// Liberate IPv6 from its walls.
		if (0 === common\mb::strpos($host, '[')) {
			$host = str_replace(array('[',']'), '', $host);
			common\ref\sanitize::ip($host, true);
		}

		// Is this an IP address? If so, we're done!
		if (filter_var($host, FILTER_VALIDATE_IP)) {
			return $host;
		}

		// Look for illegal characters. At this point we should
		// only have nice and safe ASCII.
		if (preg_match('/[^a-z\d\-\.]/u', $host)) {
			return false;
		}
		$host = explode('.', $host);
		foreach ($host as $v) {
			// Gotta have length, and can't start or end with a dash.
			if (
				!strlen($v) ||
				'-' === substr($v, 0, 1) ||
				'-' === substr($v, -1)
			) {
				return false;
			}
		}

		return implode('.', $host);
	}

	/**
	 * Parse Host Parts
	 *
	 * Break a host down into subdomain, domain, and
	 * suffix parts.
	 *
	 * @param string $host Host.
	 * @return array|bool Parts or false.
	 */
	public static function parse_host_parts($host) {
		// Tease out the hostname.
		if (false === ($host = static::parse_host($host))) {
			return false;
		}

		$out = static::HOST_PARTS;

		// If this is an IP, we don't have to do anything else.
		if (filter_var($host, FILTER_VALIDATE_IP)) {
			$out['host'] = $host;
			$out['domain'] = $host;
			return $out;
		}

		// Now the hard part. See if any parts of the host
		// correspond to a registered suffix.
		$suffixes = data::SUFFIXES;
		$suffix = array();
		$parts = explode('.', $host);
		$parts = array_reverse($parts);

		foreach ($parts as $k=>$part) {
			// Override rule.
			if (isset($suffixes[$part]) && isset($suffixes[$part]['!'])) {
				break;
			}

			// A match.
			if (isset($suffixes[$part])) {
				array_unshift($suffix, $part);
				$suffixes = $suffixes[$part];
				unset($parts[$k]);
				continue;
			}

			// A wildcard.
			if (isset($suffixes['*'])) {
				array_unshift($suffix, $part);
				$suffixes = $suffixes['*'];
				unset($parts[$k]);
				continue;
			}

			// We're done.
			break;
		}

		// The suffix can't be all there is.
		if (!count($parts)) {
			return false;
		}

		// The domain.
		$parts = array_reverse($parts);
		$out['domain'] = array_pop($parts);

		// The subdomain.
		if (count($parts)) {
			$out['subdomain'] = implode('.', $parts);
		}

		// The suffix.
		if (count($suffix)) {
			$out['suffix'] = implode('.', $suffix);
		}

		$out['host'] = $host;

		return $out;
	}

	/**
	 * Strip Leading WWW
	 *
	 * The www. subdomain is evil. This removes
	 * it, but only if it is part of the subdomain.
	 *
	 * @return bool True/false.
	 */
	public function strip_www() {
		if (!$this->is_valid() || is_null($this->subdomain)) {
			return false;
		}

		if (
			'www' === $this->subdomain ||
			'www.' === common\mb::substr($this->subdomain, 0, 4)
		) {
			$this->subdomain = preg_replace('/^www\.?/u', '', $this->subdomain);
			if (!strlen($this->subdomain)) {
				$this->subdomain = null;
			}

			$this->host = preg_replace('/^www\./u', '', $this->host);
			return true;
		}

		return false;
	}

	// --------------------------------------------------------------------- end init



	// ---------------------------------------------------------------------
	// Results
	// ---------------------------------------------------------------------

	/**
	 * Is Valid
	 *
	 * @param bool $dns Has DNS.
	 * @return bool True/false.
	 */
	public function is_valid($dns=false) {
		common\ref\cast::to_bool($dns, true);
		return !is_null($this->host) && (!$dns || $this->has_dns());
	}

	/**
	 * Is Fully Qualified Domain Name
	 *
	 * @return bool True/false.
	 */
	public function is_fqdn() {
		return (
			$this->is_valid() &&
			(is_string($this->suffix) || $this->is_ip(false))
		);
	}

	/**
	 * Is IP
	 *
	 * @param bool $restricted Allow restricted.
	 * @return bool True/false.
	 */
	public function is_ip($restricted=true) {
		common\ref\cast::to_bool($restricted, true);

		if (!$this->is_valid()) {
			return false;
		}

		if ($restricted) {
			return !!filter_var($this->host, FILTER_VALIDATE_IP);
		}

		return !!filter_var($this->host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
	}

	/**
	 * Has DNS
	 *
	 * @return bool True/false.
	 */
	public function has_dns() {
		if (is_null($this->dns)) {
			if (!$this->is_fqdn()) {
				$this->dns = false;
			}
			elseif ($this->is_ip()) {
				$this->dns = $this->is_ip(false);
			}
			else {
				$this->dns = !!filter_var(gethostbyname("{$this->host}."), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
			}
		}

		return $this->dns;
	}

	/**
	 * Is ASCII
	 *
	 * @return bool True/false.
	 */
	public function is_ascii() {
		if (!$this->is_valid()) {
			return false;
		}

		return !$this->is_unicode();
	}

	/**
	 * Is Unicode
	 *
	 * @return bool True/false.
	 */
	public function is_unicode() {
		if (!$this->is_valid() || $this->is_ip() || !function_exists('idn_to_utf8')) {
			return false;
		}

		return ($this->to_unicode('host') !== $this->host);
	}

	// --------------------------------------------------------------------- end results



	// ---------------------------------------------------------------------
	// Return Data
	// ---------------------------------------------------------------------

	/**
	 * Cast to String
	 *
	 * @return string Phone number.
	 */
	public function __toString() {
		return $this->is_valid() ? $this->host : '';
	}

	/**
	 * Magic Getter
	 *
	 * @param string $method Method name.
	 * @param mixed $args Arguments.
	 * @return mixed Variable.
	 * @throws \Exception Invalid method.
	 */
	public function __call($method, $args) {
		preg_match_all('/^get_(.+)$/', $method, $matches);
		if (
			count($matches[0]) &&
			'dns' !== $matches[1][0] &&
			property_exists($this, $matches[1][0])
		) {
			$variable = $matches[1][0];

			if (is_array($args) && count($args)) {
				$args = common\data::array_pop_top($args);
				common\ref\cast::to_bool($args);
				if ($args) {
					return $this->to_unicode($variable);
				}
			}

			return $this->{$variable};
		}

		throw new \Exception(sprintf('The required method "%s" does not exist for %s', $method, get_called_class()));
	}

	/**
	 * To Unicode
	 *
	 * @param string $key Key.
	 * @return string|null Value.
	 */
	protected function to_unicode($key) {
		$value = $this->{$key};
		if (function_exists('idn_to_utf8') && is_string($value)) {
			$value = explode('.', $value);
			$value = array_map('idn_to_utf8', $value);
			return implode('.', $value);
		}

		return $value;
	}

	/**
	 * Get Data
	 *
	 * @param bool $unicode Unicode.
	 * @return array|bool Host data or false.
	 */
	public function get_data($unicode=false) {
		common\ref\cast::to_bool($unicode, true);

		if (!$this->is_valid()) {
			return false;
		}

		return array(
			'host'=>$this->get_host($unicode),
			'subdomain'=>$this->get_subdomain($unicode),
			'domain'=>$this->get_domain($unicode),
			'suffix'=>$this->get_suffix($unicode)
		);
	}

	// --------------------------------------------------------------------- end get
}
