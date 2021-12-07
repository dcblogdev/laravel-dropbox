[![Latest Version on Packagist](https://img.shields.io/packagist/v/dcblogdev/laravel-dropbox.svg?style=flat-square)](https://packagist.org/packages/dcblogdev/laravel-dropbox)
[![Total Downloads](https://img.shields.io/packagist/dt/dcblogdev/laravel-dropbox.svg?style=flat-square)](https://packagist.org/packages/dcblogdev/laravel-dropbox)

![Logo](https://repository-images.githubusercontent.com/189828582/4defa980-49c1-11eb-9668-76f985726c80)

A Laravel package for working with Dropbox v2 API.

Dropbox API documentation can be found at:
https://www.dropbox.com/developers/documentation/http/documentation

## Application Register
To use Dropbox API an application needs creating at https://www.dropbox.com/developers/apps

Create a new application, select either Dropbox API or Dropbox Business API
Next select the type of access needed either the app folder (useful for isolating to a single folder), or full Dropbox.

Next copy and paste the APP Key and App Secret into your .env file:

```
DROPBOX_CLIENT_ID=
DROPBOX_SECRET_ID=
```
    
Now enter your desired redirect URL. This is the URL your application will use to connect to Dropbox API.

A common URL is https://domain.com/dropbox/connect

## Install

Via Composer

```
composer require dcblogdev/laravel-dropbox
```
 
## Config

You can publish the config file with:

```
php artisan vendor:publish --provider="Dcblogdev\Dropbox\DropboxServiceProvider" --tag="config"
```

When published, the config/dropbox.php config file contains, make sure to publish this file and change the scopes to match the scopes of your Dropbox app, inside Dropbox app console.

```php
<?php

return [

    /*
    * set the client id
    */
    'clientId' => env('DROPBOX_CLIENT_ID'),

    /*
    * set the client secret
    */
    'clientSecret' => env('DROPBOX_SECRET_ID'),

    /*
    * Set the url to trigger the oauth process this url should call return Dropbox::connect();
    */
    'redirectUri' => env('DROPBOX_OAUTH_URL'),

    /*
    * Set the url to redirecto once authenticated;
    */
    'landingUri' => env('DROPBOX_LANDING_URL', '/'),

    /**
     * Set access token, when set will bypass the oauth2 process
     */
    'accessToken' => env('DROPBOX_ACCESS_TOKEN', ''),

    /**
     * Set access type, options are offline and online
     * Offline - will return a short-lived access_token and a long-lived refresh_token that can be used to request a new short-lived access token as long as a user's approval remains valid.
     *
     * Online - will return a short-lived access_token
     */
    'accessType' => env('DROPBOX_ACCESS_TYPE', 'offline'),

    /*
    set the scopes to be used
    */
    'scopes' => 'account_info.read files.metadata.write files.metadata.read files.content.write files.content.read',
];
```

## Migration
You can publish the migration with:

php artisan vendor:publish --provider="Dcblogdev\Dropbox\DropboxServiceProvider" --tag="migrations"
After the migration has been published you can create the tokens tables by running the migration:

```
php artisan migrate
```

.ENV Configuration
Ensure you've set the following in your .env file:

```
DROPBOX_CLIENT_ID=
DROPBOX_SECRET_ID=
DROPBOX_OAUTH_URL=https://domain.com/dropbox/connect
DROPBOX_LANDING_URL=https://domain.com/dropbox
DROPBOX_ACCESS_TYPE=offline
```

Bypass Oauth2
You can bypass the oauth2 process by generating an access token in your dropbox app and entering it on the .env file:

```
DROPBOX_ACCESS_TOKEN=
```

## Usage
Note this package expects a user to be logged in.

Note: these examples assume the authentication is using the oauth2 and not setting the access token in the .env directly.

If setting the access code directly don't rely on Dropbox::getAccessToken()

A routes example:

```php
Route::group(['middleware' => ['web', 'auth']], function(){
    Route::get('dropbox', function(){

        if (! Dropbox::isConnected()) {
            return redirect(env('DROPBOX_OAUTH_URL'));
        } else {
            //display your details
            return Dropbox::post('users/get_current_account');
        }

    });

    Route::get('dropbox/connect', function(){
        return Dropbox::connect();
    });

    Route::get('dropbox/disconnect', function(){
        return Dropbox::disconnect('app/dropbox');
    });

});
```

Or using a middleware route, if the user does not have a graph token then automatically redirect to get authenticated:

```php
Route::group(['middleware' => ['web', 'DropboxAuthenticated']], function(){
    Route::get('dropbox', function(){
        return Dropbox::post('users/get_current_account');
    });
});

Route::get('dropbox/connect', function(){
    return Dropbox::connect();
});

Route::get('dropbox/disconnect', function(){
    return Dropbox::disconnect('app/dropbox');
});
```

Once authenticated you can call Dropbox:: with the following verbs:

```php
Dropbox::get($endpoint, $array = [], $headers = [], $useToken = true)
Dropbox::post($endpoint, $array = [], $headers = [], $useToken = true)
Dropbox::put($endpoint, $array = [], $headers = [], $useToken = true)
Dropbox::patch($endpoint, $array = [], $headers = [], $useToken = true)
Dropbox::delete($endpoint, $array = [], $headers = [], $useToken = true)
```

The $array is not always required, its requirement is determined from the endpoint being called, see the API documentation for more details.

The $headers are optional when used can pass in additional headers.

The $useToken is optional when set to true will use the authorisation header, defaults to true.

These expect the API endpoints to be passed, the URL https://api.dropboxapi.com/2/ is provided, only endpoints after this should be used ie:

```php
Dropbox::post('users/get_current_account')
```

## Middleware
To restrict access to routes only to authenticated users there is a middleware route called DropboxAuthenticated

Add DropboxAuthenticated to routes to ensure the user is authenticated:

```php
Route::group(['middleware' => ['web', 'DropboxAuthenticated'], function()
```

To access the token model reference this ORM model:

```php
use Dcblogdev\Dropbox\Models\DropboxToken;
```

## Files

This package provides a clean way of working with files.

To work with files first call ->files() followed by a method.

Import Namespace

```php
use Dcblogdev\Dropbox\Facades\Dropbox;
```

List Content

list files and folders of a given path

```php
Dropbox::files()->listContents($path = '')
```

List Content Continue

Using a cursor from the previous listContents call to paginate over the next set of folders/files.

```php
Dropbox::files()->listContentsContinue($cursor = '')
```

Delete folder/file
Pass the path to the file/folder, When delting a folder all child items will be deleted.

```php
Dropbox::files()->delete($path)
```

Create Folder
Pass the path to the folder to be created.

```php
Dropbox::files()->createFolder($path)
```

Search Files
Each word will used to search for files.

```php
Dropbox::files()->search($query)
```

Upload File
Upload files to Dropbox by passing the folder path followed by the filename. Note this method supports uploads up to 150MB only.

```php
Dropbox::files()->upload($path, $file)
```

Download File
Download file from Dropbox by passing the folder path including the file.

```php
Dropbox::files()->download($path)
```

Move Folder/File
Move accepts 4 params:

$fromPath - provide the path for the existing folder/file
$toPath - provide the new path for the existing golder/file must start with a /
$autoRename - If there's a conflict, have the Dropbox server try to autorename the file to avoid the conflict. The default for this field is false.
$allowOwnershipTransfer - Allow moves by owner even if it would result in an ownership transfer for the content being moved. This does not apply to copies. The default for this field is false.

```php
Dropbox::files()->move($fromPath, $toPath, $autoRename = false, $allowOwnershipTransfer = false);
```

## Change log

Please see the [changelog][3] for more information on what has changed recently.

## Contributing

Contributions are welcome and will be fully credited.

Contributions are accepted via Pull Requests on [Github][4].

## Pull Requests

- **Document any change in behaviour** - Make sure the `readme.md` and any other relevant documentation are kept up-to-date.

- **Consider our release cycle** - We try to follow [SemVer v2.0.0][5]. Randomly breaking public APIs is not an option.

- **One pull request per feature** - If you want to do more than one thing, send multiple pull requests.

## Security

If you discover any security related issues, please email dave@dcblog.dev email instead of using the issue tracker.

## License

license. Please see the [license file][6] for more information.

[3]:    changelog.md
[4]:    https://github.com/dcblogdev/laravel-dropbox
[5]:    http://semver.org/
[6]:    license.md
