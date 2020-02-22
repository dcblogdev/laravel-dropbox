<?php

namespace Dcblogdev\Dropbox\Resources;

use Dcblogdev\Dropbox\Dropbox;
use GuzzleHttp\Client;
use Exception;

class Files extends Dropbox
{
    public function __construct()
    {
        parent::__construct();
    }

    public function listContents($path = '')
    {
        $pathRequest = $this->forceStartingSlash($path);

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
        $path = $this->forceStartingSlash($path);

        return $this->post('files/delete_v2', [
            'path' => $path
        ]);
    }

    public function createFolder($path)
    {
        $path = $this->forceStartingSlash($path);

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

    public function upload($path, $uploadPath)
    {
        $path = $this->forceStartingSlash($path);
        $uploadPath = $this->forceStartingSlash($uploadPath);

        try {

            $fp = fopen($path, 'rb');
            $filesize = filesize($path);

            $ch = curl_init('https://content.dropboxapi.com/2/files/upload');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->getAccessToken(),
                'Content-Type: application/octet-stream',
                'Dropbox-API-Arg: ' .
                    json_encode([
                        "path" => $uploadPath,
                        "mode" => "add",
                        "autorename" => true,
                        "mute" => false
                    ])
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, fread($fp, $filesize));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            return $response;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function download($path)
    {
        $path = $this->forceStartingSlash($path);

        try {
            $client = new Client;

            $response = $client->post("https://content.dropboxapi.com/2/files/download", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
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
