<?php

return [

    /*
    * set the client id
    */
    'clientId' => env('DROPBOX_CLIENT_ID'),

    /*
    * set the client secret
    */
    'clientSecret' => env('DROPBOX_SECRET'),

    /*
    * Set the url to trigger the oauth process this url should call return MsGraph::connect();
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
];