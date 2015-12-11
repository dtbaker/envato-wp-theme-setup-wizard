# Envato WordPress Theme Setup Wizard
A step by step setup wizard that runs after a ThemeForest theme has been activated.
## Video Demo:
Best to watch this video demo to see exactly what this plugin does: https://www.youtube.com/watch?v=vMey1BrKP_A
## Setup Instructions
- Copy the `api` folder onto your web server ( e.g. yoursite.com/envato/api/ ) - it's best if this is hosted on SSL
- This API folder will handle oAuth login for automatic theme updates in the (new) Envato Market plugin.
- Register a new App at http://build.envato.com ( tick the "Download the users purchased items", and "View the users purchases of the app creators items" permissions). Put in the full URL to `server-script.php` as the confirmation URL.
- At the top of `api/server-script.php` fill in the values from your Envato app and the full URL to `server-script.php` :
```php
define('_ENVATO_APP_ID','put-your-envato-app-id-here');
define('_ENVATO_APP_SECRET','put-your-envato-app-secret-here');
define('_ENVATO_APP_URL','http://yoursite.com/envato/api/server-script.php');
```
- Copy the `envato_setup` folder into your Theme folder.
- At the top of `envato_setup/envato_setup.php` set your Envato username and the full url to your `server-script.php` file:
```php
private $envato_username = 'dtbaker';
private $oauth_script = 'http://yoursite.com/envato/api/server-script.php';
```
- Make sure TGMPA is enabled, configured and working correctly in your theme ( see http://tgmpluginactivation.com/ ). This wizard integrates with the latest version of TGM to find which plugins needs to be installed.
- Make sure the `Envato Market` plugin is added to the required plugin list in TGM, it can be added like this:
```php
        array(
            'name' => 'Envato Market',
            'slug' => 'envato-market',
            'source' => 'https://envato.github.io/wp-envato-market/dist/envato-market.zip',
            'required' => true,
            'recommended' => true,
            'force_activation' => true,
        ),
```
- `envato_setup/js/envato-setup.js` is the script which handles the "Loading Button" animation (pretty simple and cool hey?) along with processing the ajax requests for each default installation action.
- The ajax requests happen in two queries. The first ajax query from javascript will get the "Loading" text to display (e.g. "Installing Pages") along with the URL and data to perform the actual ajax task. Javascript then executes the second ajax task to actually perform the action. And then a third ajax request to confirm it worked.
- `envato_setup/content/` contains the default content that will be loaded during the wizard. e.g. `envato_setup/content/all.xml` is imported when the "Pages" default content is selected. This is handled in a callback like so:
```php
    private function _content_install_pages(){
        return $this->_import_wordpress_xml_file(__DIR__ ."/content/all.xml");
    }
```
- Adjust the data in `envato_setup/content/` to suit your theme.
- If you add `&export=true` to the URL of the setup wizard it will output some json data which can be added to the json files in `envato_setup/content/`.

## Envato Market Plugin
The Envato Market plugin is very new. Details here: https://github.com/envato/wp-envato-market
By default the Envato Market plugin requires users generate a personal token for the API. This can be time consuming and confusing for first time buyers.
This `envato-setup.php` script combined with the code in `/api/` adds a few hooks to the Envato Market plugin to enable single click oAuth login for updates. It's by far from perfect but the only way to get oAuth login working with updates.

Feel free to pull my oAuth code apart. You'll see this is a method of getting a purchase code back into the WordPress theme. So if you just need a purchase code for some other purpose then you can use this oAuth bounce to get one easily.

## Warning

This script integrates heavily into my current theme (beautiful watercolor) by pulling in configuration variables and running some other hooks/functions that are defined in my theme. This wizard will not work completely straight out of the box. But hopefully it gives you some ideas :)

## More Details.

Feel free to do a pull request with improvements.

Feel free to ask questions on the forum ( https://forums.envato.com/t/fully-automatic-plugin-install-default-content-oauth-theme-updates-in-a-wizard/20504 ) or create issues here on github.