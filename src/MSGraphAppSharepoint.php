<?php

namespace BitsnBolts\Flysystem\Adapter;

use stdClass;
use Exception;
use Microsoft\Graph\Graph;
use GuzzleHttp\Psr7\Stream;
use Microsoft\Graph\Model\Site;
use Microsoft\Graph\Model\Drive;
use Microsoft\Graph\Model\ListItem;
use Microsoft\Graph\Model\DriveItem;
use GuzzleHttp\Exception\ClientException;
use BitsnBolts\Flysystem\Adapter\MSGraph\AuthException;
use BitsnBolts\Flysystem\Adapter\MSGraph\SiteInvalidException;
use BitsnBolts\Flysystem\Adapter\MSGraph\DriveInvalidException;

class MSGraphAppSharepoint extends MSGraph
{
    protected $accessToken;

    public function __construct()
    {
        parent::__construct(self::MODE_SHAREPOINT);
    }

    public function authorize($tenantId, $clientId, $clientSecret)
    {
        $guzzle = new \GuzzleHttp\Client();
        $url = 'https://login.microsoftonline.com/' . $tenantId . '/oauth2/token?api-version=1.0';
        try {
            $token = json_decode($guzzle->post($url, [
                'form_params' => [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'resource' => 'https://graph.microsoft.com/',
                    'grant_type' => 'client_credentials',
                ],
            ])->getBody()->getContents());
        } catch (ClientException $e) {
            throw new AuthException($e->getMessage(), $e->getCode());
        }

        $this->accessToken = $token->access_token;
    }

    public function initialize($targetId, $driveName = '')
    {
        $this->graph = new Graph();
        $this->graph->setAccessToken($this->accessToken);

        try {
            $site = $this->graph->createRequest('GET', '/sites/' . $targetId)
                ->setReturnType(Site::class)
                ->execute();
            // Assign the site id triplet to our targetId
            $this->targetId = $site->getId();
        } catch (\Exception $e) {
            if ($e->getCode() === 400) {
                throw new SiteInvalidException("The sharepoint site " . $targetId . " is invalid.");
            }

            throw $e;
        }
        $this->prefix = "/sites/" . $this->targetId . '/drive/items/';
        if ($driveName !== '') {
            // Then we specified a drive name, so let's enumerate the drives and find it
            $this->setDriveByName($driveName);
        }
    }

    public function listContents($directory = '', $recursive = false)
    {
        $this->directory = $directory;
        try {
            $this->setDriveByName($directory);
            $drive = $this->graph->createRequest('GET', $this->prefix . 'root:/')
                ->setReturnType(Drive::class)
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

    public function has($path)
    {
        $this->setDriveByPath($path);
        try {
            $driveItem = $this->graph->createRequest('GET', $this->prefix . 'root:/' . $this->getFilenameFromPath($path))
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

        return false;
    }

    public function read($path)
    {
        $this->setDriveByPath($path);
        try {
            $driveItem = $this->graph->createRequest('GET', $this->prefix . 'root:/' . $this->getFilenameFromPath($path))
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

        return false;
    }

    public function getUrl($path)
    {
        $this->setDriveByPath($path);
        try {
            $driveItem = $this->graph->createRequest('GET', $this->prefix . 'root:/' . $this->getFilenameFromPath($path))
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

        return false;
    }

    public function setDriveByPath($path)
    {
        // The drive is everything before the filename.
        if (!strpos($path, '/')) {
            return;
        }

        $driveName = strstr($path, '/', true);
        $drive = $this->setDriveByName($driveName);
    }

    public function setDriveByName($driveName)
    {
        $drive = $this->getDriveByName($driveName);
        $this->setDrive($drive);
    }

    public function getDriveByName($driveName)
    {
        $drives = $this->graph->createRequest('GET', '/sites/' . $this->targetId . '/drives')
            ->setReturnType(DriveItem::class)
            ->execute();
        foreach ($drives as $drive) {
            if ($drive->getName() === $driveName) {
                return $drive;
            }
        }

        throw new DriveInvalidException("The sharepoint drive with name " . $driveName . " could not be found.");
    }

    public function setDrive($drive)
    {
        $this->driveId = $drive->getId();
        $this->prefix = "/drives/" . $this->driveId . "/items/";
    }

    public function createDrive($driveName)
    {
        return $this->graph
            ->createRequest('POST', '/sites/' . $this->targetId . '/lists')
            ->attachBody([
                'displayName' => $driveName,
                'list' => ['template' => 'documentLibrary']
            ])
            ->setReturnType(ListItem::class)
            ->execute();
    }

    public function deleteDrive($driveName)
    {
        $drive = $this->getDriveByName($driveName);
        return $this->graph
            ->createRequest('DELETE', '/sites/' . $this->targetId . '/drive/items/' . $drive->getId())
            ->execute();
    }

    protected function getFilenameFromPath($path)
    {
        $position = strrpos($path, '/');

        if ($position === false) {
            return $path;
        }

        return substr($path, $position + strlen('/'));
    }
}
