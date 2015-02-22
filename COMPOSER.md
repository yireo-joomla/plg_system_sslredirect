# Instructions for using composer

Use composer to install this extension. First make sure to initialize composer with the right settings:

    composer -n init
    composer install --no-dev

Next, modify your local composer.json file:

    {
        "repositories": [
            {"type": "composer", "url": "http://satis.yireo.com"}
        ],
        "require": {
            "yireo/plg-system-sslredirect": "dev-master"
        }
    }

Note that the Yireo extension for Joomla is not listed on Packagist, but
we use our own Satis server instead.

To install:

    composer update --no-dev

Done.

