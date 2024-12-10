<?php

declare(strict_types=1);

namespace Dcblogdev\Dropbox;

use Dcblogdev\Dropbox\Facades\Dropbox as Api;
use Dcblogdev\Dropbox\Models\DropboxToken;
use Dcblogdev\Dropbox\Resources\Files;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class Dropbox
{
    protected static string $baseUrl = 'https://api.dropboxapi.com/2/';
    protected static string $contentUrl = 'https://content.dropboxapi.com/2/';
    protected static string $authorizeUrl = 'https://www.dropbox.com/oauth2/authorize';
    protected static string $tokenUrl = 'https://api.dropbox.com/oauth2/token';
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function files(): Files
    {
        return new Files($this->request);
    }

    /**
     * __call catches all requests when no found method is requested
     *
     * @param  $function - the verb to execute
     * @param  $args - array of arguments
     * @return array request
     * @throws Exception
     */
    public function __call(string $function, array $args)
    {
        $options = ['get', 'post', 'patch', 'put', 'delete'];
        $path = (isset($args[0])) ? $args[0] : null;
        $data = (isset($args[1])) ? $args[1] : null;

        if (in_array($function, $options)) {
            return self::sendRequest($function, $path, $data);
        } else {
            //request verb is not in the $options array
            throw new Exception($function . ' is not a valid HTTP Verb');
        }
    }

    /**
     * Make a connection or return a token where it's valid
     *
     * @throws Exception
     */
    public function connect(): RedirectResponse
    {
        if ($this->request->has('error')) {
            throw new Exception('Error: '.$this->request->input('error').'<br/>Description: '.$this->request->input('error_description'));
        }

        if (!$this->request->has('code')) {

            $url = self::$authorizeUrl . '?' . http_build_query([
                'response_type' => 'code',
                'client_id' => Config::string('dropbox.clientId'),
                'redirect_uri' => Config::string('dropbox.redirectUri'),
                'scope' => Config::string('dropbox.scopes'),
                'token_access_type' => Config::string('dropbox.accessType')
            ]);

            return Redirect::away($url);
        } elseif ($this->request->has('code')) {

            // With the authorization code, we can retrieve access tokens and other data.
            try {

                $params = [
                    'grant_type'    => 'authorization_code',
                    'code'          => $this->request->input('code'),
                    'redirect_uri'  => Config::string('dropbox.redirectUri'),
                    'client_id'     => Config::string('dropbox.clientId'),
                    'client_secret' => Config::string('dropbox.clientSecret')
                ];

                $token = $this->sendFormRequest(self::$tokenUrl, $params);
                $result = $this->storeToken($token);

                $me = Api::post('users/get_current_account', null);

                if ($me['email'] === null) {
                    throw new Exception('Connect: Email not found');
                }

                //find account and add email
                $t = DropboxToken::findOrFail($result->id);
                $t->email = $me['email'];
                $t->save();

                return Redirect::to(Config::string('dropbox.landingUri'));
            } catch (Exception $e) {
                throw new Exception('Connect: '.$e->getMessage());
            }
        }
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        return !($this->getTokenData() === null);
    }

    /**
     * Disables the access token used to authenticate the call, redirects back to the provided path
     *
     * @param string $redirectPath
     * @return void
     * @throws Exception
     */
    public function disconnect(string $redirectPath = '/'): void
    {
        $id = Auth::id();
        $this->sendRequest('post', 'auth/token/revoke', null);

        $token = DropboxToken::where('user_id', $id)->first();
        if ($token !== null) {
            $token->delete();
        }

        header('Location: ' .url($redirectPath));
        exit();
    }

    /**
     * Return authenticated access token or request new token when expired
     *
     * @param  $returnNullNoAccessToken bool|null when set to true return null
     * @return string|null
     * @throws Exception
     */
    public function getAccessToken(?bool $returnNullNoAccessToken = false): ?string
    {
        //use token from .env if exists
        if (Config::string('dropbox.accessToken') !== '') {
            return Config::string('dropbox.accessToken');
        }

        $id    = Auth::id();
        $token = DropboxToken::where('user_id', $id)->first();

        // Check if tokens exist otherwise run the oauth request
        if (!isset($token->access_token)) {

            if ($returnNullNoAccessToken === true) {
                return null;
            }

            Redirect::to(Config::string('dropbox.redirectUri'));
        }

        if (isset($token->refresh_token)) {
            // Check if token is expired
            // Get current time + 5 minutes (to allow for time differences)
            $now = time() + 300;
            if ($token->expires_in <= $now) {
                // Token is expired (or very close to it) so let's refresh
                $params = [
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $token->refresh_token,
                    'client_id'     => Config::string('dropbox.clientId'),
                    'client_secret' => Config::string('dropbox.clientSecret')
                ];

                $tokenResponse       = $this->sendFormRequest(self::$tokenUrl, $params);
                $token->access_token = $tokenResponse['access_token'];
                $token->expires_in   = now()->addseconds($tokenResponse['expires_in']);
                $token->save();

                return $token->access_token;
            }
        }

        // Token is still valid, just return it
        return $token->access_token;
    }

    /**
     * Get token data
     *
     * @return DropboxToken|null
     */
    public function getTokenData(): ?DropboxToken
    {
        //use token from .env if exists
        if (Config::string('dropbox.accessToken') !== '') {
            return null;
        }

        $id = Auth::id();
        return DropboxToken::where('user_id', $id)->first();
    }

    /**
     * Store token
     *
     * @param array $tokenData
     * @return object
     */
    protected function storeToken(array $tokenData)
    {
        $id = Auth::id();

        $data = [
            'user_id'       => $id,
            'access_token'  => $tokenData['access_token'],
            'expires_in'    => now()->addseconds($tokenData['expires_in']),
            'token_type'    => $tokenData['token_type'],
            'uid'           => $tokenData['uid'],
            'account_id'    => $tokenData['account_id'],
            'scope'         => $tokenData['scope']
        ];

        if (isset($token['refresh_token'])) {
            $data['refresh_token'] = $token['refresh_token'];
        }

        //create a new record or if the user id exists update record
        return DropboxToken::updateOrCreate(['user_id' => $id], $data);
    }

    /**
     * Send request to Dropbox API
     *
     * @param  $type string
     * @param  $request string
     * @param array|null $data array
     * @return array
     * @throws Exception
     */
    protected function sendRequest(string $type, string $request, ?array $data): ?array
    {
        $response = Http::withToken($this->getAccessToken())->$type(self::$baseUrl . $request, $data);

        if ($response->failed()) {
            $this->handleError($response);
        }

        return $response->json();
    }

    /**
     * Send a POST request to Dropbox API with form data.
     *
     * @param string $url
     * @param array $params
     * @return array
     * @throws Exception
     */
    protected function sendFormRequest(string $url, array $params): array
    {
        $response = Http::asForm()->post($url, $params);

        if ($response->failed()) {
            $this->handleError($response);
        }

        return $response->json();
    }

    /**
     * Handle non-successful responses with detailed error logging.
     *
     * @param $response
     * @throws Exception
     */
    protected function handleError($response)
    {
        $status = $response->status();
        $errorBody = $response->body();
        $errorMessage = "HTTP error {$status}: {$errorBody}";

        // Provide user-friendly messages for specific status codes
        switch ($status) {
            case 400:
                throw new Exception("Bad Request: {$errorBody}");
            case 401:
                throw new Exception("Unauthorized: Please check your API token.");
            case 403:
                throw new Exception("Forbidden: Access denied.");
            case 404:
                throw new Exception("Not Found: The requested resource was not found.");
            case 500:
            case 503:
                throw new Exception("Server Error: Please try again later.");
            default:
                throw new Exception($errorMessage);
        }
    }

    protected function forceStartingSlash(string $string): string
    {
        if (substr($string, 0, 1) !== "/") {
            $string = "/$string";
        }

        return $string;
    }
}
