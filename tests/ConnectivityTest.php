<?php
namespace BitsnBolts\Flysystem\Adapter\MSGraph\Test;

use BitsnBolts\Flysystem\Adapter\MSGraph\AuthException;
use BitsnBolts\Flysystem\Adapter\MSGraph\DriveInvalidException;
use BitsnBolts\Flysystem\Adapter\MSGraph\SiteInvalidException;
use BitsnBolts\Flysystem\Adapter\MSGraph\ModeException;

use BitsnBolts\Flysystem\Adapter\MSGraphAppSharepoint;

class ConnectivityTest extends TestBase
{
    /**
     * Tests if an exception is properly thrown when unable to connect to
     * Microsoft Graph service due to invalid credentials.
     *
     * @test
     */
    public function testAuthFailure()
    {
        $this->expectException(AuthException::class);
        $adapter = new MSGraphAppSharepoint();
        $adapter->authorize(TENANT_ID, 'invalid', 'invalid');
    }

    /**
     * Tests if an exception is properly thrown when a sharepoint site specified is invalid.
     *
     * @test
     */
    public function testInvalidSiteSpecified()
    {
        $this->expectException(SiteInvalidException::class);
        $adapter = new MSGraphAppSharepoint();
        $adapter->authorize(TENANT_ID, APP_ID, APP_PASSWORD);
        $adapter->initialize('invalid', SHAREPOINT_DRIVE_NAME);
    }

    /**
     * Tests if an exception is properly thrown when a sharepoint drive specified is invalid.
     *
     * @test
     */
    public function testInvalidDriveSpecified()
    {
        $this->expectException(DriveInvalidException::class);
        $adapter = new MSGraphAppSharepoint();
        $adapter->authorize(TENANT_ID, APP_ID, APP_PASSWORD);
        $adapter->initialize(SHAREPOINT_SITE_ID, 'invalid');
    }

    /**
     * Tests to ensure that the adapter is successfully created which is a result of
     * valid authentication with access token retrieved.
     *
     * @test
     */
    public function testAuthSuccess()
    {
        $adapter = new MSGraphAppSharepoint();
        $adapter->authorize(TENANT_ID, APP_ID, APP_PASSWORD);
        $this->assertNotNull($adapter);
    }

    /**
     * Tests to ensure that the adapter is successfully created which is a result of
     * valid authentication with access token retrieved.
     *
     * @test
     */
    public function testInitializeSuccess()
    {
        $adapter = new MSGraphAppSharepoint();
        $adapter->authorize(TENANT_ID, APP_ID, APP_PASSWORD);
        $adapter->initialize(SHAREPOINT_SITE_ID, SHAREPOINT_DRIVE_NAME);
        $this->assertNotNull($adapter);
    }
}
