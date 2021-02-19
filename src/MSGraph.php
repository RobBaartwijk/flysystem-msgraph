<?php

namespace BitsnBolts\Flysystem\Adapter;

use League\Flysystem\Adapter\CanOverwriteFiles;
use BitsnBolts\Flysystem\Adapter\MSGraph\ModeException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Stream;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;
use Microsoft\Graph\Model;
use Microsoft\Graph\Model\DriveItem;
use Microsoft\Graph\Model\UploadSession;

class MSGraph extends AbstractAdapter implements CanOverwriteFiles
{
    const MODE_SHAREPOINT = 'sharepoint';

    const MODE_ONEDRIVE = 'onedrive';

    // Our mode, if sharepoint or onedrive
    protected $mode;

    // Our Microsoft Graph Client
    protected $graph;

    // Our Microsoft Graph Access Token
    protected $token;

    // Our targetId, sharepoint site if sharepoint, drive id if onedrive
    protected $targetId;

    // Our driveId, which if non empty points to a Drive
    protected $driveId;

    // Our url prefix to be used for most file operations. This gets created in our constructor
    protected $prefix;

    public function __construct($mode)
    {
        if ($mode != self::MODE_ONEDRIVE && $mode != self::MODE_SHAREPOINT) {
            throw new ModeException("Unknown mode specified: " . $mode);
        }

        $this->mode = $mode;
    }

    public function has($path)
    {
        if ($this->mode == self::MODE_SHAREPOINT) {
            try {
                $driveItem = $this->graph->createRequest('GET', $this->prefix . 'root:/' . $path)
                    ->setReturnType(DriveItem::class)
                    ->execute();
                // Successfully retrieved meta data.
                return true;
            } catch (ClientException $e) {
                if ($e->getCode() == 404) {
                    // Not found, let's return false;
                    return false;
                }

                throw $e;
            } catch (Exception $e) {
                throw $e;
            }
        }

        return false;
    }

    public function read($path)
    {
        if ($this->mode == self::MODE_SHAREPOINT) {
            try {
                $driveItem = $this->graph->createRequest('GET', $this->prefix . 'root:/' . $path)
                    ->setReturnType(DriveItem::class)
                    ->execute();
                // Successfully retrieved meta data.
                // Now get content
                $contentStream = $this->graph->createRequest('GET', $this->prefix . $driveItem->getId() . '/content')
                    ->setReturnType(Stream::class)
                    ->execute();
                $contents = '';
                $bufferSize = 8012;
                // Copy over the data into a string
                while (! $contentStream->eof()) {
                    $contents .= $contentStream->read($bufferSize);
                }

                return ['contents' => $contents];
            } catch (ClientException $e) {
                if ($e->getCode() == 404) {
                    // Not found, let's return false;
                    return false;
                }

                throw $e;
            } catch (Exception $e) {
                throw $e;
            }
        }

        return false;
    }

    public function getUrl($path)
    {
        if ($this->mode == self::MODE_SHAREPOINT) {
            try {
                $driveItem = $this->graph->createRequest('GET', $this->prefix . 'root:/' . $path)
                    ->setReturnType(DriveItem::class)
                    ->execute();
                // Successfully retrieved meta data.
                // Return url property
                return $driveItem->getWebUrl();
            } catch (ClientException $e) {
                if ($e->getCode() == 404) {
                    // Not found, let's return false;
                    return false;
                }

                throw $e;
            } catch (Exception $e) {
                throw $e;
            }
        }

        return false;
    }

    public function readStream($path)
    {
    }

    public function listContents($directory = '', $recursive = false)
    {
        if ($this->mode == self::MODE_SHAREPOINT) {
            try {
                $drive = $this->graph->createRequest('GET', $this->prefix . 'root:/' . $directory)
                    ->setReturnType(Model\Drive::class)
                    ->execute();
                // Successfully retrieved meta data.
                // Now get content
                $driveItems = $this->graph->createRequest('GET', $this->prefix . $drive->getId() . '/children')
                    ->setReturnType(DriveItem::class)
                    ->execute();

                $normalizer = [$this, 'normalizeResponse'];
                $normalized = array_map($normalizer, $driveItems);
                return $normalized;
            } catch (ClientException $e) {
                throw $e;
            } catch (Exception $e) {
                throw $e;
            }
        }

        return [];
    }

    public function getMetadata($path)
    {
        $driveItem = $this->getDriveItem($path);
        return $this->normalizeResponse($driveItem);
    }

    private function getDriveItem($path): DriveItem
    {
        return $this->graph->createRequest('GET', $this->prefix . 'root:/' . $path)
             ->setReturnType(DriveItem::class)
             ->execute();
    }

    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    public function getVisibility($path)
    {
    }

    // Write methods
    public function write($path, $contents, Config $config = null)
    {
        if ($this->mode == self::MODE_SHAREPOINT) {
            // Attempt to write to sharepoint
            $driveItem = $this->graph->createRequest('PUT', $this->prefix . 'root:/' . $path . ':/content')
                ->attachBody($contents)
                ->setReturnType(DriveItem::class)
                ->execute();

            // Successfully created
            return true;
        }

        return false;
    }

    public function writeStream($path, $contents, Config $config)
    {
        $stat = fstat($contents);
        if ($stat['size'] <= 4000000) {
            return $this->write($path, stream_get_contents($contents), $config);
        }

        // Files over 4mb should use an upload session.
        $uploadSession = $this->graph->createRequest('POST', $this->prefix . 'root:/' . $path . ':/createUploadSession')
            ->setReturnType(UploadSession::class)
            ->execute();

        $guzzle = new \GuzzleHttp\Client();
        $guzzle->put($uploadSession->getUploadUrl(), [
            'body' => stream_get_contents($contents),
            'headers' => [
                'Content-Range' => $range = sprintf('bytes 0-%d/%d', $stat['size'] -1, $stat['size']),
                'Content-Length' => $stat['size']
            ]])
            ->getBody()
            ->getContents();
        return true;
    }

    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    public function updateStream($path, $resource, Config $config)
    {
        $this->writeStream($path, $resource, $config);
    }

    public function rename($path, $newpath)
    {
    }

    public function copy($path, $newpath)
    {
    }

    public function delete($path)
    {
        if ($this->mode == self::MODE_SHAREPOINT) {
            try {
                $driveItem = $this->graph->createRequest('GET', $this->prefix . 'root:/' . $path)
                    ->setReturnType(DriveItem::class)
                    ->execute();
                // Successfully retrieved meta data.
                // Now delete the file
                $this->graph->createRequest('DELETE', $this->prefix . $driveItem->getId())
                    ->execute();

                return true;
            } catch (ClientException $e) {
                if ($e->getCode() == 404) {
                    // Not found, let's return false;
                    return false;
                }

                throw $e;
            } catch (Exception $e) {
                throw $e;
            }
        }

        return false;
    }

    public function deleteDir($dirname)
    {
    }

    public function createDir($dirname, Config $config)
    {
    }

    public function setVisibility($path, $visibility)
    {
    }

    /**
     * Normalize the object result array.
     *
     * @param  DriveItem  $response
     *
     * @return array
     */
    protected function normalizeResponse(DriveItem $response)
    {
        return [
            'path'       => $response->getName(),
            'linkingUrl' => $response->getWebUrl(),
            'timestamp'  => $response->getLastModifiedDateTime()->format('U'),
            'created'    => $response->getCreatedDateTime()->format('U'),
            'dirname'    => '',
            'mimetype'   => $response->getFile()->getMimeType(),
            'size'       => $response->getSize(),
            'type'       => 'file',
        ];
    }

    public function inviteUser($path, $username)
    {
        $driveItem = $this->getDriveItem($path);
        if ($this->mode == self::MODE_SHAREPOINT) {
            try {
                $data = [
                    'requireSignIn' => true,
                    'sendInvitation' => false,
                    'roles' => ['read', 'write'],
                    'recipients' => [
                         [ "email" => $username ]
                    ],
                    'message' => 'Welkom'
                ];
                $invitation = $this->graph->createRequest('POST', $this->prefix . $driveItem->getId() .'/invite')
                    ->attachBody($data)
                    ->setReturnType(Model\SharingInvitation::class)
                    ->execute();
                // Successfully retrieved meta data.
                return $invitation;
            } catch (ClientException $e) {
                if ($e->getCode() == 404) {
                    // Not found, let's return false;
                    return false;
                }

                throw $e;
            } catch (Exception $e) {
                throw $e;
            }
        }
        return false;
    }
}
