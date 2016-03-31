<?php
namespace Binky;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputInterface;

class Application extends SymfonyApplication {
    private $cmd;

    public function __construct(Command $cmd) {
        $this->cmd = $cmd;

        parent::__construct('Binky', 'v0.1');
    }

    protected function getCommandName(InputInterface $input) {
        return $this->cmd->getName();
    }

    protected function getDefaultCommands() {
        $defaults = parent::getDefaultCommands();

        $defaults[] = $this->cmd;

        return $defaults;
    }

    public function getDefinition() {
        $inputDefinition = parent::getDefinition();
        $inputDefinition->setArguments();

        return $inputDefinition;
    }
}