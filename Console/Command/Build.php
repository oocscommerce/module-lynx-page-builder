<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Console\Command;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Module\ModuleListInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Installs and builds the editor bundle of every enabled module that ships one: PageBuilder and its extensions
 */
class Build extends Command
{
    /**
     * Where a module keeps the editor's Vite workspace, beside web/ rather than in it (see docs/pagebuilder.md)
     */
    public const WORKSPACE_DIR = 'view/adminhtml/pagebuilder';

    private const OPTION_NPM = 'npm';

    /**
     * Registrar, module list, file driver, name
     *
     * @param ComponentRegistrarInterface $registrar
     * @param ModuleListInterface $moduleList
     * @param FileDriver $fileDriver
     * @param string|null $name
     */
    public function __construct(
        private readonly ComponentRegistrarInterface $registrar,
        private readonly ModuleListInterface $moduleList,
        private readonly FileDriver $fileDriver,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    /**
     * Name, description and input
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('lynx:pagebuilder:build')
            ->setDescription('npm ci and build the editor bundle of PageBuilder and of every enabled extension')
            ->addOption(
                self::OPTION_NPM,
                null,
                InputOption::VALUE_REQUIRED,
                'The npm binary to run, e.g. npm12 or /usr/bin/npm12',
                'npm'
            );

        parent::configure();
    }

    /**
     * Run npm ci and npm run build in every workspace, stopping at the first failure
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspaces = $this->workspaces();

        if ($workspaces === []) {
            $output->writeln(\sprintf(
                '<comment>No enabled module has a %s/package.json to build.</comment>',
                self::WORKSPACE_DIR
            ));

            return Command::SUCCESS;
        }

        $npm = (string) $input->getOption(self::OPTION_NPM);

        foreach ($workspaces as $module => $dir) {
            $output->writeln(\sprintf('<info>%s</info>', $module));

            // ci, not install: installs exactly the lock file, which every workspace must commit
            if (!$this->runNpm($output, $npm, ['ci'], $dir)
                || !$this->runNpm($output, $npm, ['run', 'build'], $dir)
            ) {
                return Command::FAILURE;
            }
        }

        $output->writeln(\sprintf('<info>Built %d bundle(s).</info>', \count($workspaces)));
        $output->writeln('Run <info>setup:static-content:deploy</info> to publish them to pub/static.');

        return Command::SUCCESS;
    }

    /**
     * The enabled modules that carry an editor workspace, in load order so PageBuilder comes before its extensions
     *
     * @return array<string, string> Module name to workspace path
     */
    private function workspaces(): array
    {
        $paths = $this->registrar->getPaths(ComponentRegistrar::MODULE);
        $workspaces = [];

        foreach ($this->moduleList->getNames() as $module) {
            $dir = ($paths[$module] ?? '') . '/' . self::WORKSPACE_DIR;

            if (isset($paths[$module]) && $this->fileDriver->isFile($dir . '/package.json')) {
                $workspaces[$module] = $dir;
            }
        }

        return $workspaces;
    }

    /**
     * Run npm and pass its output straight through
     *
     * @param OutputInterface $output
     * @param string $npm
     * @param string[] $arguments
     * @param string $cwd
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function runNpm(OutputInterface $output, string $npm, array $arguments, string $cwd): bool
    {
        $output->writeln(\sprintf('<comment>%s %s</comment>  (%s)', $npm, implode(' ', $arguments), $cwd));

        $process = new Process([$npm, ...$arguments], $cwd);
        $process->setTimeout(null);

        try {
            $process->run(static function (string $type, string $buffer) use ($output): void {
                $output->write($buffer);
            });
        } catch (\Throwable $e) {
            $output->writeln(\sprintf('<error>%s</error>', $e->getMessage()));

            return false;
        }

        if (!$process->isSuccessful()) {
            $output->writeln(\sprintf('<error>%s exited with %d.</error>', $npm, (int) $process->getExitCode()));

            return false;
        }

        return true;
    }
}
