<?php

namespace Daveismyname\Dropbox\Resources;

use Daveismyname\Dropbox\Dropbox;
use GuzzleHttp\Client;
use Exception;

class Files extends Dropbox
{
    public function __construct() {
        parent::__construct();
    }

    public function listContents($path = '')
    {
        $pathRequest = $this->ForceStartingSlash($path);

        return $this->post('files/list_folder', [
            'path' => $path == '' ? '' : $pathRequest
        ]);
    }

    public function listContentsContinue($cursor = '')
    {
        return $this->post('files/list_folder/continue', [
            'cursor' => $cursor
        ]);
    }

    public function delete($path)
    {
        $path = $this->ForceStartingSlash($path);

        return $this->post('files/delete_v2', [
            'path' => $path
        ]);
    }

    public function createFolder($path)
    {
        $path = $this->ForceStartingSlash($path);

        return $this->post('files/create_folder', [
            'path' => $path
        ]);
    }

    public function search($query)
    {
        return $this->post('files/search', [
            'path' => '',
            'query' => $query,
            'start' => 0,
            'max_results' => 1000,
            'mode' => 'filename'
        ]);
    }

    public function upload($path, $file)
    {
        $path = $this->ForceStartingSlash($path);

        try {
            $client = new Client;

            $response = $client->post("https://content.dropboxapi.com/2/files/upload", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->getAccessToken(),
                    'Dropbox-API-Arg' => json_encode([
                        'path' => $path,
                        'mode' => 'add',
                        'autorename' => true
                    ]),
                    'Content-Type' => 'application/octet-stream',
                    'data-binary' => "@$file"
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);

        } catch (Exception $e) {
            throw new Exception($e->getResponse()->getBody()->getContents());
        }
    }

    public function download($path)
    {
        $path = $this->ForceStartingSlash($path);

        try {
            $client = new Client;

            $response = $client->post("https://content.dropboxapi.com/2/files/download", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->getAccessToken(),
                    'Dropbox-API-Arg' => json_encode([
                        'path' => $path
                    ])
                ]
            ]);

            return $response->getBody()->getContents();

        } catch (Exception $e) {
            throw new Exception($e->getResponse()->getBody()->getContents());
        }
    }
}