# blob-domain

[![Build Status](https://travis-ci.org/Blobfolio/blob-domain.svg?branch=master)](https://travis-ci.org/Blobfolio/blob-domain)

blob-domain uses the [Public Suffix List](https://publicsuffix.org/list/) to construct and validate the suffix portion of a domain name.

This repository will be updated monthly, but if you need more up-to-date sources, you can recompile the database files locally by running the included build script:

```php
//run it in the terminal of your choice:
php build/build.php
```

This will store local copies of the data it retrieves in `build/src`.

An updated `lib/blobfolio/domain/data.php` file will be generated at the end of the build process.