<?php
namespace Binky;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class Application extends SymfonyApplication {
    private $cmd;

    public function __construct(Command $cmd) {
        $this->cmd = $cmd;

        parent::__construct('Binky', 'v0.2');
    }

    protected function getCommandName(InputInterface $input) {
        return $this->cmd->getName();
    }

    protected function getDefaultCommands() {
        return array_merge(parent::getDefaultCommands(), [$this->cmd]);
    }

    public function getDefinition() {
        $inputDefinition = parent::getDefinition();
        $inputDefinition->setArguments();

        return $inputDefinition;
    }

    protected function getDefaultInputDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('command', InputArgument::REQUIRED, 'The command to execute'),

            new InputOption('--help', '-h', InputOption::VALUE_NONE, 'Display this help message'),
            new InputOption('--quiet', '-q', InputOption::VALUE_NONE, 'Do not output any message'),
            new InputOption('--verbose', '-v', InputOption::VALUE_NONE, 'Display the entire message; contents and headers'),
            new InputOption('--version', '', InputOption::VALUE_NONE, 'Display this application version'),
            new InputOption('--ansi', '', InputOption::VALUE_NONE, 'Force ANSI output'),
            new InputOption('--no-ansi', '', InputOption::VALUE_NONE, 'Disable ANSI output'),
            new InputOption('--no-interaction', '-n', InputOption::VALUE_NONE, 'Do not ask any interactive question'),
        ));
    }
}