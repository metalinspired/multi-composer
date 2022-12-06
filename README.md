# Composer plugin that helps with dependencies for projects that have multiple composer instances

If you're stuck in situation where a project has to have two (or more) composer instances, and they require
(and install) same packages this plugin will help you by reusing installed packages from one of those instances.

It achieves this by reading the `composer.lock` and generating (and injecting) a list of provided and conflicting
packages.

## Installation

1. Add your source composer instance as repository to `composer.json`

       "repositories": [
          {
            "type": "path",
            "url": "../where_other_composer_is_installed",
            "options": {
              "symlink": true
            }
          }
       ]

2. Install this plugin (confirm with "y" that you trust and want to enable the plugin)

       composer require metalinspired/multi-composer

3. Configure the plugin

       "extra": {
         "multi-composer": [
           {
             "package": "your-vendor-name/your-project-name:dev-main",
             "autoload_psr-4": false,
             "skip_dev": false
           }
         ]
       }

4. Require your source composer instance

       composer require your-vendor-name/your-project-name:dev-main

## Configuration options

`package`: String with name and version of your source composer instance.
This must be provided in the same form as when requiring your source composer instance (project-name:version)

`autoload_psr-4`: Optional boolean indicating whether to include PSR-4 namespace(s)
defined in your source composer instance.

*Note:* This only works when you install (request) your source composer instance. Changing the value of this and
updating will have no effect. You will have to remove and require again (reinstall does not work) your source
composer instance.

`skip_dev`: Optional boolean indicating should *dev* dependencies be skipped. This is useful if your source composer
instance has same *dev* requirements but does not have them installed.