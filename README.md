# Lynx Page Builder

`oocscommerce/module-lynx-page-builder`

The visual editor for the Lynx storefront.

## Features

- Designs with theme settings: colors, fonts, corner radius, borders, shadows and page width
- Drag and drop components into the header, content and footer of every page type
- Live preview, with sample data for pages such as the order view or checkout success
- Share a preview with a link and password
- Choose the active design per store view in Stores > Configuration > Lynx > Theme Settings

## Installation

Part of [Lynx](https://docs.oocscommerce.com/lynx/), installed with the metapackage:

```bash
composer require oocscommerce/magento2-lynx-community
```

See [Installation](https://docs.oocscommerce.com/lynx/developer/installation) for the full setup.

## Building the editor

The editor ships prebuilt in `view/adminhtml/web/js/pagebuilder/`. Rebuild it only after changing its sources in `view/adminhtml/pagebuilder`, or for an enabled extension module whose editor bundle is not prebuilt:

```bash
bin/magento lynx:pagebuilder:build
bin/magento setup:static-content:deploy
```

The command runs `npm ci` and `npm run build` in the editor of this module and of every enabled extension, stopping at the first failure. It needs Node.js 22.12+ and npm; `--npm` names another npm binary, e.g. `--npm=/usr/bin/npm22`.

## Dependencies

- [`oocscommerce/module-lynx-component`](https://github.com/oocscommerce/module-lynx-component)
- [`oocscommerce/module-lynx-inertia`](https://github.com/oocscommerce/module-lynx-inertia)

## Documentation

https://docs.oocscommerce.com/lynx/

Building on Lynx with Claude Code: the [Lynx skills](https://github.com/oocscommerce/claude-skills) teach it to write components, providers, checkout methods and more.

## Community

Questions, ideas and help: join [Lynx on Slack](https://join.slack.com/t/lynxmagento/shared_invite/zt-4dd3wdz3k-DILaGAowU_p3nevNLJ1gbw).

## License

[OSL-3.0](LICENSE.txt) or [AFL-3.0](LICENSE_AFL.txt).
