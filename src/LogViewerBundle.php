<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle;

use Danilovl\LogViewerBundle\DependencyInjection\LogViewerExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class LogViewerBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        return new LogViewerExtension;
    }
}
