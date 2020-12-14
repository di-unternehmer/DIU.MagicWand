<?php
namespace DIU\MagicWand\ResourceManagement;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\ResourceManagement\Target\FileSystemSymlinkTarget;
use DIU\MagicWand\Domain\Service\ConfigurationService;

class ProxyAwareFileSystemSymlinkTarget extends FileSystemSymlinkTarget implements ProxyAwareTargetInterface
{
    use ProxyAwareTargetTrait;

    /**
     * @Flow\Inject
     * @var Bootstrap
     */
    protected $bootstrap;

    /**
     * @var UriBuilder
     * @Flow\Inject
     */
    protected $uriBuilder;

    /**
     * @var ResourceManager
     * @Flow\Inject
     */
    protected $resourceManager;

    /**
     * @var ConfigurationService
     * @Flow\Inject
     */
    protected $configurationService;
}
