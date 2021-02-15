<?php
namespace BitsnBolts\Flysystem\Adapter\MSGraph\Test;

use League\Flysystem\Filesystem;
use BitsnBolts\Flysystem\Adapter\Plugins\CreateDrive;
use BitsnBolts\Flysystem\Adapter\Plugins\DeleteDrive;
use BitsnBolts\Flysystem\Adapter\MSGraphAppSharepoint;
use BitsnBolts\Flysystem\Adapter\Plugins\GetUrl;

class SharepointTest extends TestBase
{
    private $fs;

    private $filesToPurge = [];

    protected function setUp(): void
    {
        parent::setUp();
        $adapter = new MSGraphAppSharepoint();
        $adapter->authorize(TENANT_ID, APP_ID, APP_PASSWORD);
        $adapter->initialize(SHAREPOINT_SITE_ID, SHAREPOINT_DRIVE_NAME);

        $this->fs = new Filesystem($adapter);
    }

    public function testWrite()
    {
        $this->assertEquals(true, $this->fs->write(TEST_FILE_PREFIX . 'testWrite.txt', 'testing'));
        $this->filesToPurge[] = TEST_FILE_PREFIX . 'testWrite.txt';
    }

    public function testDelete()
    {
        // Create file
        $this->fs->write(TEST_FILE_PREFIX . 'testDelete.txt', 'testing');
        // Ensure it exists
        $this->assertEquals(true, $this->fs->has(TEST_FILE_PREFIX . 'testDelete.txt'));
        // Now delete
        $this->assertEquals(true, $this->fs->delete(TEST_FILE_PREFIX . 'testDelete.txt'));
        // Ensure it no longer exists
        $this->assertEquals(false, $this->fs->has(TEST_FILE_PREFIX . 'testDelete.txt'));
    }

    public function testHas()
    {
        // Test that file does not exist
        $this->assertEquals(false, $this->fs->has(TEST_FILE_PREFIX . 'testHas.txt'));

        // Create file
        $this->fs->write(TEST_FILE_PREFIX . 'testHas.txt', 'testing');
        $this->filesToPurge[] = TEST_FILE_PREFIX . 'testHas.txt';

        // Test that file exists
        $this->assertEquals(true, $this->fs->has(TEST_FILE_PREFIX . 'testHas.txt'));
    }

    public function testRead()
    {
        // Create file
        $this->fs->write(TEST_FILE_PREFIX . 'testRead.txt', 'testing read functionality');
        $this->filesToPurge[] = TEST_FILE_PREFIX . 'testRead.txt';

        // Call read
        $this->assertEquals("testing read functionality", $this->fs->read(TEST_FILE_PREFIX . 'testRead.txt'));
    }

    public function testGetUrl()
    {
        // Create file
        $this->fs->write(TEST_FILE_PREFIX . 'testGetUrl.txt', 'testing getUrl functionality');
        $this->filesToPurge[] = TEST_FILE_PREFIX . 'testGetUrl.txt';

        // Get url
        $this->assertNotEmpty($this->fs->getAdapter()->getUrl(TEST_FILE_PREFIX . 'testGetUrl.txt'));
    }

    public function testGetUrlPlugin()
    {
        $this->fs->addPlugin(new GetUrl());

        $this->fs->write(TEST_FILE_PREFIX . 'testGetUrlPlugin.txt', 'testing getUrl plugin functionality');
        $this->filesToPurge[] = TEST_FILE_PREFIX . 'testGetUrlPlugin.txt';

        // Get url
        $this->assertNotEmpty($this->fs->getAdapter()->getUrl(TEST_FILE_PREFIX . 'testGetUrlPlugin.txt'));
    }

    public function testCreateAndDeleteDrive()
    {
        $this->fs->addPlugin(new CreateDrive());
        $this->fs->addPlugin(new DeleteDrive());

        $adapter = new MSGraphAppSharepoint();
        $adapter->authorize(TENANT_ID, APP_ID, APP_PASSWORD);
        $adapter->initialize(SHAREPOINT_SITE_ID);

        $this->fs->createDrive('testNewDrive');

        $this->assertNotNull($adapter);

        $this->fs->deleteDrive('testNewDrive');
    }

    /**
     * Tears down the test suite by attempting to delete all files written, clearing things up
     *
     * @todo Implement functionality
     */
    protected function tearDown(): void
    {
        foreach ($this->filesToPurge as $path) {
            try {
                $this->fs->delete($path);
            } catch (\Exception $e) {
                // Do nothing, just continue. We obviously can't clean it
            }
        }
        $this->filesToPurge = [];
    }
}
