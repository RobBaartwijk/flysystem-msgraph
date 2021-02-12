<?php

namespace BitsnBolts\Flysystem\Adapter;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Stream;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use BitsnBolts\Flysystem\Adapter\MSGraph\ModeException;
use BitsnBolts\Flysystem\Adapter\MSGraph\SiteInvalidException;

class MSGraph extends AbstractAdapter
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
    }

    public function has($path)
    {
        if ($this->mode == self::MODE_SHAREPOINT) {
            try {
                $driveItem = $this->graph->createRequest('GET', $this->prefix . 'root:/' . $path)
                    ->setReturnType(Model\DriveItem::class)
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
                    ->setReturnType(Model\DriveItem::class)
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
                    ->setReturnType(Model\DriveItem::class)
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
                    ->setReturnType(Model\DriveItem::class)
                    ->execute();

                $normalizer = [$this, 'normalizeResponse'];
                $normalized = array_map($normalizer, $driveItems);
                return $normalized;
//                $children = [];
//                foreach ($driveItems as $driveItem) {
//                    $item = $driveItem->getProperties();
//                    $item['path'] = $directory . '/' . $driveItem->getName();
//                    $children[] = $item;
//                }

                return $children;
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
    }

    public function getSize($path)
    {
    }

    public function getMimetype($path)
    {
    }

    public function getTimestamp($path)
    {
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
                ->setReturnType(Model\DriveItem::class)
                ->execute();

            // Successfully created
            return true;
        }

        return false;
    }

    public function writeStream($path, $resource, Config $config)
    {
    }

    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    public function updateStream($path, $resource, Config $config)
    {
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
                    ->setReturnType(Model\DriveItem::class)
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
     * @param array  $response
     *
     * @return array
     */
    protected function normalizeResponse($response)
    {
        return [
            'path'       => $response->getName(),
            'linkingUrl' => $response->getWebUrl(),
            'timestamp'  => $response->getLastModifiedDateTime()->format('U'),
            'dirname'    => $response->getParentReference()->getPath(),
            'mimetype'   => $response->getFile()->getMimeType(),
            'size'       => $response->getSize(),
            'type'       => 'file',
        ];
    }

    public function inviteUser($path, $username)
    {
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
                $invitation = $this->graph->createRequest('POST', $this->prefix . $path .'/invite')
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
