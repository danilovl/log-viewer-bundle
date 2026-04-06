<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Command;

use Danilovl\LogViewerBundle\Parser\Reader\LogViewer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'danilovl:log-viewer:cache-clear',
    description: 'Clear the log-viewer-bundle cache.'
)]
class CacheClearCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'danilovl.log_viewer.cache')]
        private readonly TagAwareCacheInterface $cache
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cache->invalidateTags([LogViewer::CACHE_TAG]);

        $output->writeln('Cache for log-viewer-bundle successfully cleared using tags.');

        return Command::SUCCESS;
    }
}
