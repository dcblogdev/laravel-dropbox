<?php

namespace Dcblogdev\Dropbox;

use Dcblogdev\Dropbox\Facades\Dropbox as Api;
use Dcblogdev\Dropbox\Models\DropboxToken;
use Dcblogdev\Dropbox\Resources\Files;

use GuzzleHttp\Client;
use Exception;

class Dropbox
{
    protected static $baseUrl = 'https://api.dropboxapi.com/2/';
    protected static $contentUrl = 'https://content.dropboxapi.com/2/';
    protected static $authorizeUrl = 'https://www.dropbox.com/oauth2/authorize';
    protected static $tokenUrl = 'https://api.dropbox.com/oauth2/token';

    public function __construct()
    {
    }

    public function files()
    {
        return new Files();
    }

    protected function forceStartingSlash($string)
    {
        if (substr($string, 0, 1) !== "/") {
            $string = "/$string";
        }

        return $string;
    }

    /**
     * __call catches all requests when no founf method is requested
     * @param  $function - the verb to execute
     * @param  $args - array of arguments
     * @return gizzle request
     */
    public function __call($function, $args)
    {
        $options = ['get', 'post', 'patch', 'put', 'delete'];
        $path = (isset($args[0])) ? $args[0] : null;
        $data = (isset($args[1])) ? $args[1] : null;
        $customHeaders = (isset($args[2])) ? $args[2] : null;
        $useToken = (isset($args[3])) ? $args[3] : true;

        if (in_array($function, $options)) {
            return self::guzzle($function, $path, $data, $customHeaders, $useToken);
        } else {
            //request verb is not in the $options array
            throw new Exception($function . ' is not a valid HTTP Verb');
        }
    }

    /**
     * Make a connection or return a token where it's valid
     * @return mixed
     */
    public function connect()
    {
        //when no code param redirect to Microsoft
        if (!request()->has('code')) {

            $url = self::$authorizeUrl . '?' . http_build_query([
                'response_type' => 'code',
                'client_id' => config('dropbox.clientId'),
                'redirect_uri' => config('dropbox.redirectUri')
            ]);

            return redirect()->away($url);
        } elseif (request()->has('code')) {

            // With the authorization code, we can retrieve access tokens and other data.
            try {

                $params = [
                    'grant_type'    => 'authorization_code',
                    'code'          => request('code'),
                    'redirect_uri'  => config('dropbox.redirectUri'),
                    'client_id'     => config('dropbox.clientId'),
                    'client_secret' => config('dropbox.clientSecret')
                ];

                $token = $this->dopost(self::$tokenUrl, $params);
                $result = $this->storeToken($token);

                //get user details
                $me = Api::post('users/get_current_account');

                //find record and add email - not required but useful none the less
                $t = DropboxToken::findOrFail($result->id);
                $t->email = $me['email'];
                $t->save();

                return redirect(config('dropbox.landingUri'));
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }
    }

    /**
     * Return authenticated access token or request new token when expired
     * @param  $id integer - id of the user
     * @param  $returnNullNoAccessToken null when set to true return null
     * @return return string access token
     */
    public function getAccessToken($returnNullNoAccessToken = null)
    {
        //use token from .env if exists
        if (config('dropbox.accessToken') !== '') {
            return config('dropbox.accessToken');
        }

        //use id if passed otherwise use logged in user
        $id    = auth()->id();
        $token = DropboxToken::where('user_id', $id)->first();

        // Check if tokens exist otherwise run the oauth request
        if (!isset($token->access_token)) {

            //don't redirect simply return null when no token found with this option
            if ($returnNullNoAccessToken == true) {
                return null;
            }

            header('Location: ' . config('dropbox.redirectUri'));
            exit();
        }

        // Token is still valid, just return it
        return $token->access_token;
    }

    /**
     * @param  $id - integar id of user
     * @return object
     */
    public function getTokenData()
    {
        //use token from .env if exists
        if (config('dropbox.accessToken') !== '') {
            return false;
        }

        $id = auth()->id();
        return DropboxToken::where('user_id', $id)->first();
    }

    /**
     * Store token
     * @param  $access_token string
     * @param  $refresh_token string
     * @param  $expires string
     * @param  $id integer
     * @return object
     */
    protected function storeToken($token)
    {
        $id = auth()->id();

        //cretate a new record or if the user id exists update record
        return DropboxToken::updateOrCreate(['user_id' => $id], [
            'user_id'       => $id,
            'access_token'  => $token['access_token'],
            'token_type'    => $token['token_type'],
            'uid'           => $token['uid'],
            'account_id'    => $token['account_id']
        ]);
    }

    /**
     * run guzzle to process requested url
     * @param  $type string
     * @param  $request string
     * @param  $data array
     * @param  $id integer
     * @return json object
     */
    protected function guzzle($type, $request, $data = [], $customHeaders = null, $useToken = true)
    {
        try {
            $client = new Client;

            $headers = [
                'content-type' => 'application/json'
            ];

            if ($useToken == true) {
                $headers['Authorization'] = 'Bearer ' . $this->getAccessToken();
            }

            if ($customHeaders !== null) {
                foreach ($customHeaders as $key => $value) {
                    $headers[$key] = $value;
                }
            }

            $response = $client->$type(self::$baseUrl . $request, [
                'headers' => $headers,
                'body' => json_encode($data)
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            throw new Exception($e->getResponse()->getBody()->getContents());
        }
    }

    protected static function dopost($url, $params)
    {
        try {
            $client = new Client;
            $response = $client->post($url, ['form_params' => $params]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            return json_decode($e->getResponse()->getBody()->getContents(), true);
        }
    }
}
