# blob-domain

blob-domain is a simple PHP library for parsing and validating domain names. It supports the full [Public Suffix](https://publicsuffix.org/list/) ruleset, translates Unicode to ASCII or vice versa (if the PHP extension INTL is present), and can break down a host into its constituent parts: subdomain, domain, and suffix.

[![Build Status](https://travis-ci.org/Blobfolio/blob-domain.svg?branch=master)](https://travis-ci.org/Blobfolio/blob-domain)

&nbsp;

## Table of Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Reference](#reference)
 * [::parse_host()](#parse_host)
 * [::parse_host_parts()](#parse_host_parts)
 * [is_valid()](#is_valid)
 * [is_ascii()](#is_ascii)
 * [is_fqdn()](#is_fqdn)
 * [is_ip()](#is_ip)
 * [is_unicode()](#is_unicode)
 * [has_dns()](#has_dns)
4. [License](#license)

&nbsp;

## Requirements

blob-domain and its dependencies require PHP 7+ with the following modules:

 * BCMath
 * DOM
 * Fileinfo
 * Filter
 * JSON
 * MBString
 * INTL

&nbsp;

## Installation

Use Composer:

```bash
composer require "blobfolio/blob-domain:dev-master"
```

&nbsp;

## Reference

First up, the basics.

### Basic Usage:

```php
$domain = new blobfolio\domain\domain('www.Google.com');

// Access the sanitized host by typecasting as a string.
echo (string) $domain; //www.google.com

// Get it all. If a part is not applicable, its value will be NULL.
$data = $domain->get_data();
/*
array(
    host => www.google.com
    subdomain => www
    domain => google
    suffix => com
)
*/

// Or each part using `get_KEY()`.
echo $domain->get_host(); //www.google.com
echo $domain->get_subdomain(); //www
echo $domain->get_domain(); //google
echo $domain->get_suffix(); //com

// By default, Unicode domains are converted to ASCII.
$domain = new blobfolio\domain\domain('http://☺.com');
echo $domain->get_host(); //xn--74h.com

// Convert them back by passing `TRUE` to the getters.
echo $domain->get_host(true); //☺.com
```

&nbsp;

The following static methods exist if you didn't want to initialize an object.

### ::parse_host()

Parse the hostname part of an arbitrary string, which might be a hostname, IP address, URL, etc.

Note: this will convert Unicode to ASCII.

#### Arguments

 * (*string*) Host

#### Returns

Returns the processed hostname or `FALSE` on failure.

#### Example

```php
$foo = blobfolio\domain\domain::parse_host('http://☺.com'); //xn--74h.com
$foo = blobfolio\domain\domain::parse_host('co.uk'); //FALSE
```



### ::parse_host_parts()

Pull out the different parts of a host name, i.e. what you'd get running `get_data()` on a `domain` object.

Note: this will convert Unicode to ASCII.

#### Arguments

 * (*string*) Host

#### Returns

Returns an array containing the processed parts or `FALSE` on failure.

#### Example

```php
$foo = blobfolio\domain\domain::parse_host_parts('www.Google.com');
/*
array(
    host => www.google.com
    subdomain => www
    domain => google
    suffix => com
)
*/
```

&nbsp;

The object also comes with public methods. Aside from the various `get_*()` functions already demonstrated, you've got the following.

### is_valid()

Is the host valid? This will be `TRUE` unless:
 * The host string is malformed;
 * The host string contains Unicode and the INTL extension is missing;
 * The host contains no domain portion;
 * The `$dns` option is passed but the host is not a FQDN or is missing an `A` record;

#### Arguments

 * (*bool*) (*optional*) Reachable DNS. If `TRUE`, the host must either be a public IP address or have an `A` record in its DNS table. Default: `FALSE`

#### Returns

Returns `TRUE` or `FALSE`.



### is_ascii()

Whether or not a host is ASCII, like `google.com`. Unicode domains are still a bit unknown in many parts of the Western world and can cause problems with native PHP functions or databases, so good to know what you've got.

Note: The various `get_*()` functions will always return the host in ASCII format by default. Passing `TRUE` to those functions will return Unicode hosts in their original Unicode, e.g. `☺.com`.

#### Arguments

N/A

#### Returns

Returns `TRUE` or `FALSE`.



### is_fdqn()

Checks to see whether the host is a Fully-Qualified Domain Name (or at least a public IP address). In other words, can it be seen by the outside world?

Note: this does not imply the host actually exists.

#### Arguments

N/A

#### Returns

Returns `TRUE` or `FALSE`.



### is_ip()

Is the host an IP address?

#### Arguments

 * (*bool*) (*optional*) Allow restricted/private. Default: `TRUE`

#### Returns

Returns `TRUE` or `FALSE`.



### is_unicode()

Whether or not a host is Unicode, like `☺.com`. Unicode hosts can cause problems with native PHP functions and databases, so might be a good thing to know.

Note: The various `get_*()` functions will return an ASCII-fied version of a Unicode domain by default, like `xn--74h.com`. Passing `TRUE` will de-convert them back to the original Unicode.

#### Arguments

N/A

#### Returns

Returns `TRUE` or `FALSE`.



### has_dns()

Does this host have an `A` record in its DNS table or is it a public IP address?

#### Arguments

N/A

#### Returns

Returns `TRUE` or `FALSE`.



&nbsp;

## License

Copyright © 2017 [Blobfolio, LLC](https://blobfolio.com) &lt;hello@blobfolio.com&gt;

This work is free. You can redistribute it and/or modify it under the terms of the Do What The Fuck You Want To Public License, Version 2.

    DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
    Version 2, December 2004
    
    Copyright (C) 2017 Sam Hocevar <sam@hocevar.net>
    
    Everyone is permitted to copy and distribute verbatim or modified
    copies of this license document, and changing it is allowed as long
    as the name is changed.
    
    DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
    TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION
    
    0. You just DO WHAT THE FUCK YOU WANT TO.
