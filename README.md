[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

This library provides an abstract manager pattern for creating extensible service managers with support for multiple connections, configuration management, and dynamic driver registration. It's designed for managing services like caches, databases, API clients, or any resource that requires connection pooling and driver abstraction.

## Requirements

> **Requires [PHP 8.4+](https://php.net/releases/)**

## Installation

```bash
composer require cline/manager
```

## Documentation

- **[Quickstart Guide](cookbooks/quickstart.md)** - Complete implementation example with Laravel
- **[AbstractManager Pattern](cookbooks/abstract-manager.md)** - Core manager functionality and usage
- **[ManagerInterface Pattern](cookbooks/manager-interface.md)** - Interface contract details
- **[ConnectorInterface Pattern](cookbooks/connector-interface.md)** - Connector implementation guide

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- [Brian Faust][link-maintainer]
- [All Contributors][link-contributors]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://github.com/faustbrian/manager/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/manager.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/manager.svg

[link-tests]: https://github.com/faustbrian/manager/actions
[link-packagist]: https://packagist.org/packages/cline/manager
[link-downloads]: https://packagist.org/packages/cline/manager
[link-security]: https://github.com/faustbrian/manager/security
[link-maintainer]: https://github.com/faustbrian
[link-contributors]: ../../contributors
