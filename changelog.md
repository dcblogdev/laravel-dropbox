# Changelog

All notable changes to `Laravel Dropbox` will be documented in this file.

## Version 1.0.0

- Everything

## Version 1.0.1

Added landing url option

## Version 1.0.2

Added support for Laravel 6

## Version 1.0.3

Fixed 0 size upload. Uploads now correctly uploads the contents of a file.

## Version 2.0.0

Vendor name change

## Version 2.0.1

Added support for Laravel 7

## Version 2.0.2

added support for Laravel 8

## Version 2.0.3

added support for Guzzle 7

## Version 2.0.4

Improved upload method

$path can be empty so set the desired folder structure for storing uploaded files.

## Version 2.0.5

added support for scopes to the config file

## Version 3.0.0

This major release has some breaking changes additional columns added to the migration added:
* refresh_token
* scope

Changed expires_in to DATETIME type.

added new methods:

**isConnected** returns true when there is token data.

```php
Dropbox::isConnected()
```

**disconnect()** disconnects from Dropbox and deleted the token then redirects to the path provided, defaults to /

```php
Dropbox::disconnect($redirectPath = '/')
```

Added new config option, 

Set access type, options are **offline** and **online**
     * **Offline** - will return a short-lived access_token and a long-lived refresh_token that can be used to request a new short-lived access token as long as a user's approval remains valid.
     * **Online** - will return a short-lived access_token 

```php
'accessType' => env('DROPBOX_ACCESS_TYPE', 'offline')
```

Added support for refresh tokens, now when a token is about to expire and there is a refresh token stored, a new access_token will be refreshed by using the refresh token this happens automatically when any request to Dropbox is attempted.

## Version 3.0.2 

Improved download method, request a path, it will be downloaded.

## Version 3.0.3

added a move() method to the files() resource Latest
Move accepts 4 params:

- `$fromPath` - provide the path for the existing folder/file
- `$toPath` - provide the new path for the existing golder/file must start with a /
- `$autoRename` - If there's a conflict, have the Dropbox server try to autorename the file to avoid the conflict. The default for this field is false.
$allowOwnershipTransfer - Allow moves by owner even if it would result in an ownership transfer for the content being moved. This does not apply to copies. The default for this field is false.

```php
Dropbox::files()->move($fromPath, $toPath, $autoRename = false, $allowOwnershipTransfer = false); 
```

## Version 3.0.4

added support for Laravel 9
