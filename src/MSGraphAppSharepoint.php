<?php

namespace BitsnBolts\Flysystem\Adapter;

use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\Site;
use GuzzleHttp\Exception\ClientException;
use BitsnBolts\Flysystem\Adapter\MSGraph\AuthException;
use BitsnBolts\Flysystem\Adapter\MSGraph\SiteInvalidException;

class MSGraphAppSharepoint extends MSGraph
{
    protected $mode = self::MODE_SHAREPOINT;

    protected $accessToken;

    public function __construct()
    {
        // Assign graph instance
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

    public function initialize($targetId, $driveName)
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
            if ($e->getCode() == 400) {
                throw new SiteInvalidException("The sharepoint site " . $targetId . " is invalid.");
            }

            throw $e;
        }
        $this->prefix = "/sites/" . $this->targetId . '/drive/items/';
        if ($driveName != '') {
            // Then we specified a drive name, so let's enumerate the drives and find it
            $drives = $this->graph->createRequest('GET', '/sites/' . $this->targetId . '/drives')
                ->execute();
            $drives = $drives->getBody()['value'];
            foreach ($drives as $drive) {
                if ($drive['name'] == $driveName) {
                    $this->driveId = $drive['id'];
                    $this->prefix = "/drives/" . $this->driveId . "/items/";

                    break;
                }
            }
            if (! $this->driveId) {
                throw new SiteInvalidException("The sharepoint drive with name " . $driveName . " could not be found.");
            }
        }
    }
}
