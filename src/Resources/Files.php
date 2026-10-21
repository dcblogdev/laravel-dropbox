<?php

namespace Dcblogdev\Dropbox\Resources;

use Dcblogdev\Dropbox\Dropbox;
use GuzzleHttp\Client;
use Exception;

use function PHPUnit\Framework\throwException;
use function trigger_error;

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

    public function move($fromPath, $toPath, $autoRename = false, $allowOwnershipTransfer = false)
    {
        $this->post('files/move_v2', [
            "from_path" => $fromPath,
            "to_path" => $toPath,
            "autorename" => $autoRename,
            "allow_ownership_transfer" => $allowOwnershipTransfer
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

    public function upload($path, $uploadPath, $mode = 'add')
    {
        if ($uploadPath == '') {
            throw new Exception('File is required');
        }

        $fileSize = filesize($uploadPath);

        // Dropbox simple upload limit is 150 MB.
        // Use upload sessions for larger files.
        if ($fileSize > 140 * 1024 * 1024) {
            return $this->uploadLarge(
                $path,
                $uploadPath,
                $mode
            );
        }

	    $path     = ($path !== '') ? $this->forceStartingSlash($path) : '';
	    $contents = $this->getContents($uploadPath);
        $filename = $this->getFilenameFromPath($uploadPath);
        $path     = $path.$filename;

        try {

            $ch = curl_init('https://content.dropboxapi.com/2/files/upload');
            $headers = [
                'Authorization: Bearer ' . $this->getAccessToken(),
                'Content-Type: application/octet-stream',
                'Dropbox-API-Arg: ' .
                    json_encode([
                        "path" => $path,
                        "mode" => $mode,
                        "autorename" => true,
                        "mute" => false
                    ])
            ];
            foreach ($this->getPathRootHeaders() as $name => $value) {
                $headers[] = $name . ': ' . $value;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $contents);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            return $response;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function download($path, $destFolder = '')
    {
        $path = $this->forceStartingSlash($path);

        try {
            $client = $this->createHttpClient();

            $response = $client->post("https://content.dropboxapi.com/2/files/download", [
                'headers' => array_merge([
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Dropbox-API-Arg' => json_encode([
                        'path' => $path
                    ])
                ], $this->getPathRootHeaders())
            ]);

            $header = json_decode($response->getHeader('Dropbox-Api-Result')[0], true);
            $body = $response->getBody()->getContents();

            if (empty($destFolder)){
                $destFolder = 'dropbox-temp';

                if (! is_dir($destFolder)) {
                    mkdir($destFolder);
                }
            }

            file_put_contents($destFolder.$header['name'], $body);

            return response()->download($destFolder.$header['name'], $header['name'])->deleteFileAfterSend();

        } catch (ClientException $e) {
            throw new Exception($e->getResponse()->getBody()->getContents());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function getContentsFile($path)
    {
        $path = $this->forceStartingSlash($path);

        try {
            $client = $this->createHttpClient();

            $response = $client->post("https://content.dropboxapi.com/2/files/download", [
                'headers' => array_merge([
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Dropbox-API-Arg' => json_encode([
                                                         'path' => $path
                                                     ])
                ], $this->getPathRootHeaders())
            ]);

            return $response->getBody()->getContents();

        } catch (ClientException $e) {
            throw new Exception($e->getResponse()->getBody()->getContents());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    protected function getFilenameFromPath($filePath)
    {
        $parts = explode('/', $filePath);
        $filename = end($parts);
        return $this->forceStartingSlash($filename);
    }

    protected function getContents($filePath)
    {
        return file_get_contents($filePath);
    }


    protected function uploadLarge($path, $uploadPath, $mode = 'add')
    {
        if (!is_file($uploadPath)) {
            throw new \Exception('File not found');
        }

        $chunkSize = 8 * 1024 * 1024; // 8 MB
        $fileSize = filesize($uploadPath);
        $filename = $this->getFilenameFromPath($uploadPath);
        $path = ($path !== '')
            ? $this->forceStartingSlash($path)
            : '';

        $destination = $path . $filename;

        $file = fopen($uploadPath, 'rb');

        if ($file === false) {
            throw new \Exception('Cannot open file');
        }

        $request = function ($endpoint, $data, $args) {
            $ch = curl_init(
                'https://content.dropboxapi.com/2/' . $endpoint
            );

            $headers = [
                'Authorization: Bearer ' . $this->getAccessToken(),
                'Content-Type: application/octet-stream',
                'Dropbox-API-Arg: ' . json_encode($args),
            ];

            foreach ($this->getPathRootHeaders() as $name => $value) {
                $headers[] = $name . ': ' . $value;
            }

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_RETURNTRANSFER => true,
            ]);

            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response === false || $status >= 400) {
                throw new \Exception(
                    "Dropbox upload failed ($status): " .
                    ($response ?: $error)
                );
            }

            return json_decode($response, true);
        };

        try {
            $firstChunk = fread($file, $chunkSize);

            $session = $request(
                'files/upload_session/start',
                $firstChunk,
                ['close' => false]
            );

            $sessionId = $session['session_id'];
            $offset = strlen($firstChunk);

            while ($offset < $fileSize) {
                $chunk = fread($file, $chunkSize);

                if ($chunk === false || $chunk === '') {
                    throw new \Exception('Unexpected end of file');
                }

                $length = strlen($chunk);

                if ($offset + $length === $fileSize) {
                    return $request(
                        'files/upload_session/finish',
                        $chunk,
                        [
                            'cursor' => [
                                'session_id' => $sessionId,
                                'offset' => $offset,
                            ],
                            'commit' => [
                                'path' => $destination,
                                'mode' => $mode,
                                'autorename' => true,
                                'mute' => false,
                            ],
                        ]
                    );
                }

                $request(
                    'files/upload_session/append_v2',
                    $chunk,
                    [
                        'cursor' => [
                            'session_id' => $sessionId,
                            'offset' => $offset,
                        ],
                        'close' => false,
                    ]
                );

                $offset += $length;
            }

            return $request(
                'files/upload_session/finish',
                '',
                [
                    'cursor' => [
                        'session_id' => $sessionId,
                        'offset' => $offset,
                    ],
                    'commit' => [
                        'path' => $destination,
                        'mode' => $mode,
                        'autorename' => true,
                        'mute' => false,
                    ],
                ]
            );
        } finally {
            fclose($file);
        }
    }

}
