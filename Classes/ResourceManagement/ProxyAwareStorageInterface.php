<?php
declare(strict_types=1);

namespace DIU\MagicWand\ResourceManagement;

use Neos\Flow\ResourceManagement\ResourceMetaDataInterface;

interface ProxyAwareStorageInterface
{
    public function resourceIsPresentInStorage(ResourceMetaDataInterface $resource): bool;
}
