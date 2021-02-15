<?php

namespace BitsnBolts\Flysystem\Adapter;

use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\Site;
use Microsoft\Graph\Model\DriveItem;
use GuzzleHttp\Exception\ClientException;
use BitsnBolts\Flysystem\Adapter\MSGraph\AuthException;
use BitsnBolts\Flysystem\Adapter\MSGraph\SiteInvalidException;
use Exception;
use Microsoft\Graph\Model\Drive;
use Microsoft\Graph\Model\ListItem;
use stdClass;
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
}
