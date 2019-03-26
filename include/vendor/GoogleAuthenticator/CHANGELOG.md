# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [2.2.0](https://github.com/sonata-project/GoogleAuthenticator/compare/2.1.0...2.2.0) - 2018-07-19
### Added
- Added `GoogleAuthenticatorInterface`

## [2.1.0](https://github.com/sonata-project/GoogleAuthenticator/compare/2.0.0...2.1.0) - 2018-04-09
### Added
- Added `GoogleQrUrl::generate` which contains more validation checks and is more compatible with what Google provides
- Added `Sonata\GoogleAuthenticator` namespace to all classes

### Changed
- Deprecated `GoogleAuthenticator::getUrl()` in favor of `GoogleQrUrl::generate()`

### Deprecated
- Deprecated old `Google\Authenticator` namespace

### Fixed
- Use dynamic pass code length when padding TFA codes

### Removed
- Support for old versions of PHP and Symfony.

### Security
- Constant time comparison when checking TOTP codes

## [2.0.0](https://github.com/sonata-project/GoogleAuthenticator/compare/1.1.0...2.0.0) - 2017-06-05
### Removed
- PHP 5.3 through 5.5 support was removed
- support for PHP < 7 was removed
- extending classes from the package is no longer possible

## [1.1.0](https://github.com/sonata-project/GoogleAuthenticator/compare/1.0.2...1.1.0) - 2017-04-19
### Changed
- Changed `getUrl` adding a new parameter : `$issuer`

### Deprecated
- Extending any class defined in the package is deprecated

### Fixed
- paths in sample code have been fixed

### Removed
- internal test classes are now excluded from the autoloader

### Security
- now generation of the secret is cryptographically strong.
- Implemented constant time codes comparison
