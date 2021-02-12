<?php

namespace BitsnBolts\Flysystem\Adapter\Plugins;

use League\Flysystem\FilesystemInterface;
use League\Flysystem\PluginInterface;

class DeleteDrive implements PluginInterface
{
    protected $filesystem;

    public function setFilesystem(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function getMethod()
    {
        return 'deleteDrive';
    }

    public function handle($driveName = null)
    {
        $adapter = $this->filesystem->getAdapter();
        if (is_a($adapter, \League\Flysystem\Cached\CachedAdapter::class) && $adapter->getAdapter()) {
            $adapter = $adapter->getAdapter();
        }
        $adapter->deleteDrive($driveName);
    }
}
