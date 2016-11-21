# Envato WordPress Theme Setup Wizard
A step by step setup wizard that runs after a ThemeForest theme has been activated.
## Video Demo:
Best to watch this video demo to see exactly what this plugin does:
[![ScreenShot](https://img.youtube.com/vi/vMey1BrKP_A/0.jpg)](https://www.youtube.com/watch?v=vMey1BrKP_A)

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
- Copy the `envato_setup` folder into your private $envato_usernameheme folder.
- In your theme's `functions.php` set your Envato username and the full url to your `server-script.php` file via filters:,
```php
// Please don't forgot to change filters tag.
// It must start from your theme's name.
add_filter('twentyfifteen_theme_setup_wizard_username', 'twentyfifteen_set_theme_setup_wizard_username', 10);
if( ! function_exists('twentyfifteen_set_theme_setup_wizard_username') ){
    function twentyfifteen_set_theme_setup_wizard_username($username){
        return 'dtbaker';
    }
}

add_filter('twentyfifteen_theme_setup_wizard_oauth_script', 'twentyfifteen_set_theme_setup_wizard_oauth_script', 10);
if( ! function_exists('twentyfifteen_set_theme_setup_wizard_oauth_script') ){
    function twentyfifteen_set_theme_setup_wizard_oauth_script($oauth_url){
        return 'http://yoursite.com/envato/api/server-script.php';
    }
}
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
- `envato_setup/content/` contains the default content that will be loaded during the wizard. e.g. `envato_setup/content/default.json` contains all posts and custom post types.
- If you add `&export=true` to the URL of the first page setup wizard it will output the required json data into the `envato_setup/content/` folder, it will also put media files into a local `images/stock/` folder.
- If you're looking to change what meta fields get exported have a look here: https://github.com/dtbaker/envato-wp-theme-setup-wizard/blob/master/envato_setup/envato-setup-export.php#L54 
- If you need to replace post ids, urls or shortcode content that is stored in a post meta field, look at the _elementor_id_import function here: https://github.com/dtbaker/envato-wp-theme-setup-wizard/blob/master/envato_setup/envato_setup.php#L1890  
- The `_parse_gallery_shortcode_content` function is what replaces URL's, gallery shortcode id's and contact-form-7 id's. This function is run on some meta fields as well (e.g. Elementor can store shortcodes and section content in meta fields, need to replace content in here)

## Site Styles

You can setup multiple styles for your site. This lets you export different content/images/options/posts/etc.. and the user can pick which one they want during the setup wizard.

The installer reads the `dtbwp_site_style` theme mod value like this:
 
```
get_theme_mod( 'dtbwp_site_style', $this->get_default_theme_style() );
```

Change the available styles in envato_setup.php here:
```
$this->site_styles = array(
    'style1' => 'Style 1',
    'style2' => 'Style 2',
);
```
If you only have 1 style you can set this to an empty array.

Place the logo/thumbnail for each style into the `envato_setup/images/styleX/` folders. 


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