<?php

namespace BitsnBolts\Flysystem\Adapter\Plugins;

use League\Flysystem\FilesystemInterface;
use League\Flysystem\PluginInterface;

class CreateDrive implements PluginInterface
{
    protected $filesystem;

    public function setFilesystem(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function getMethod()
    {
        return 'createDrive';
    }

    public function handle($driveName = null)
    {
        $adapter = $this->filesystem->getAdapter();
        if (is_a($adapter, \League\Flysystem\Cached\CachedAdapter::class) && $adapter->getAdapter()) {
            $adapter = $adapter->getAdapter();
        }
        $adapter->createDrive($driveName);
    }
}
