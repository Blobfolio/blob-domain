<?php
/**
 * Domain tests.
 *
 * PHPUnit tests for \blobfolio\domain\domain.
 *
 * @package blobfolio/domain
 * @author	Blobfolio, LLC <hello@blobfolio.com>
 */

/**
 * Test Suite
 */
class domain_tests extends \PHPUnit\Framework\TestCase {

	/**
	 * ::parse_host()
	 *
	 * @return void Nothing.
	 */
	function test_parse_host() {
		$things = array(
			'http://☺.com',
			'//☺.com',
			'☺.com',
			'☺.com.',
			'.☺.com'
		);

		foreach ($things as $thing) {
			$host = \blobfolio\domain\domain::parse_host($thing);
			if (function_exists('idn_to_ascii')) {
				$this->assertEquals('xn--74h.com', $host);
			}
			else {
				$this->assertEquals('☺.com', $host);
			}
		}

		$thing = 'localhost';
		$this->assertEquals($thing, \blobfolio\domain\domain::parse_host($thing));

		$thing = 'http://josh:here@[2600:3c00::f03c:91ff:feae:0ff2]:443/foobar';
		$result = \blobfolio\domain\domain::parse_host($thing);
		$this->assertEquals('2600:3c00::f03c:91ff:feae:ff2', $result);

		$thing = '-localhost';
		$this->assertEquals(false, \blobfolio\domain\domain::parse_host($thing));

		$thing = 'local_host';
		$this->assertEquals(false, \blobfolio\domain\domain::parse_host($thing));

		$thing = ' localhost';
		$this->assertEquals('localhost', \blobfolio\domain\domain::parse_host($thing));
	}

	/**
	 * ::parse_host_parts()
	 *
	 * @return void Nothing.
	 */
	function test_parse_host_parts() {
		$things = array(
			'.com'=>false,
			'eXample.com'=>array(
				'host'=>'example.com',
				'subdomain'=>null,
				'domain'=>'example',
				'suffix'=>'com'
			),
			'www.example.com'=>array(
				'host'=>'www.example.com',
				'subdomain'=>'www',
				'domain'=>'example',
				'suffix'=>'com'
			),
			'www.example.co.uk'=>array(
				'host'=>'www.example.co.uk',
				'subdomain'=>'www',
				'domain'=>'example',
				'suffix'=>'co.uk'
			),
			'co.uk'=>false,
			'www.example.sch.uk'=>array(
				'host'=>'www.example.sch.uk',
				'subdomain'=>null,
				'domain'=>'www',
				'suffix'=>'example.sch.uk'
			)
		);

		foreach ($things as $k=>$v) {
			$parts = \blobfolio\domain\domain::parse_host_parts($k);
			$this->assertEquals($v, $parts);
		}
	}

	/**
	 * ->__toString()
	 *
	 * @return void Nothing.
	 */
	function test_toString() {
		$thing = 'www.example.sch.uk';
		$result = new \blobfolio\domain\domain($thing);
		$result = (string) $result;
		$this->assertEquals($thing, $result);

		$thing = 'com';
		$result = new \blobfolio\domain\domain($thing);
		$result = (string) $result;
		$this->assertEquals('', $result);
	}

	/**
	 * ->is_valid()
	 *
	 * @return void Nothing.
	 */
	function test_is_valid() {
		$thing = new \blobfolio\domain\domain('example.com');
		$this->assertEquals(true, $thing->is_valid());

		$thing = new \blobfolio\domain\domain('com');
		$this->assertEquals(false, $thing->is_valid());

		// FQDN.
		$thing = new \blobfolio\domain\domain('blobfolio.com');
		$this->assertEquals(true, $thing->is_valid(true));

		// IP.
		$thing = new \blobfolio\domain\domain('127.0.0.1');
		$this->assertEquals(true, $thing->is_valid());

		// Local IP (e.g. not FQDN).
		$thing = new \blobfolio\domain\domain('127.0.0.1');
		$this->assertEquals(false, $thing->is_valid(true));
	}

	/**
	 * ->is_ascii()
	 *
	 * @return void Nothing.
	 */
	function test_is_ascii() {
		$things = array(
			'☺.com'=>false,
			'xn--74h.com'=>false,
			'com'=>false,
			'google.com'=>true,
			'127.0.0.1'=>true
		);

		foreach ($things as $k=>$v) {
			$thing = new \blobfolio\domain\domain($k);
			$this->assertEquals($v, $thing->is_ascii());
		}
	}

	/**
	 * ->is_fqdn()
	 *
	 * @return void Nothing.
	 */
	function test_is_fqdn() {
		$things = array(
			'example.com'=>true,
			'com'=>false,
			'localhost'=>false,
			'127.0.0.1'=>false,
			'2600:3c00::f03c:91ff:feae:0ff2'=>true
		);

		foreach ($things as $k=>$v) {
			$thing = new \blobfolio\domain\domain($k);
			$this->assertEquals($v, $thing->is_fqdn());
		}
	}

	/**
	 * ->is_ip()
	 *
	 * @return void Nothing.
	 */
	function test_is_ip() {
		$thing = new \blobfolio\domain\domain('example.com');
		$this->assertEquals(false, $thing->is_ip());

		$thing = new \blobfolio\domain\domain('example.com');
		$this->assertEquals(false, $thing->is_ip(false));

		$thing = new \blobfolio\domain\domain('127.0.0.1');
		$this->assertEquals(true, $thing->is_ip());

		$thing = new \blobfolio\domain\domain('127.0.0.1');
		$this->assertEquals(false, $thing->is_ip(false));

		$thing = new \blobfolio\domain\domain('2600:3c00::f03c:91ff:feae:0ff2');
		$this->assertEquals(true, $thing->is_ip());
	}

	/**
	 * ->is_unicode()
	 *
	 * @return void Nothing.
	 */
	function test_is_unicode() {
		$things = array(
			'☺.com'=>true,
			'xn--74h.com'=>true,
			'com'=>false,
			'google.com'=>false,
			'127.0.0.1'=>false
		);

		foreach ($things as $k=>$v) {
			$thing = new \blobfolio\domain\domain($k);
			$this->assertEquals($v, $thing->is_unicode());
		}
	}

	/**
	 * ->has_dns()
	 *
	 * @return void Nothing.
	 */
	function test_has_dns() {
		$things = array(
			'blobfolio.com'=>true,
			'asdfasfd.blobfolio.com'=>false,
			'127.0.0.1'=>false,
			'2600:3c00::f03c:91ff:feae:0ff2'=>true
		);

		foreach ($things as $k=>$v) {
			$thing = new \blobfolio\domain\domain($k);
			$this->assertEquals($v, $thing->has_dns());
		}
	}

	/**
	 * ->get_data()
	 *
	 * @return void Nothing.
	 */
	function test_get_data() {
		$things = array(
			'.com'=>false,
			'eXample.com'=>array(
				'host'=>'example.com',
				'subdomain'=>null,
				'domain'=>'example',
				'suffix'=>'com'
			),
			'www.example.com'=>array(
				'host'=>'www.example.com',
				'subdomain'=>'www',
				'domain'=>'example',
				'suffix'=>'com'
			),
			'☺.com'=>array(
				'host'=>'xn--74h.com',
				'subdomain'=>null,
				'domain'=>'xn--74h',
				'suffix'=>'com'
			)
		);

		foreach ($things as $k=>$v) {
			$result = new \blobfolio\domain\domain($k);
			$this->assertEquals($v, $result->get_data());
		}

		if (function_exists('idn_to_utf8')) {
			// Test Unicode.
			$expected = array(
				'host'=>'☺.com',
				'subdomain'=>null,
				'domain'=>'☺',
				'suffix'=>'com'
			);
			$thing = new \blobfolio\domain\domain('☺.com');
			$this->assertEquals($expected, $thing->get_data(true));
		}
	}

	/**
	 * ->__get()
	 *
	 * @return void Nothing.
	 */
	function test_get() {
		$thing = new \blobfolio\domain\domain('eXample.com');

		$this->assertEquals('example.com', $thing->get_host());
		$this->assertEquals(null, $thing->get_subdomain());
		$this->assertEquals('example', $thing->get_domain());
		$this->assertEquals('com', $thing->get_suffix());

		$thing = new \blobfolio\domain\domain('☺.com');
		$this->assertEquals('☺.com', $thing->get_host(true));
		$this->assertEquals('xn--74h.com', $thing->get_host());
	}
}


