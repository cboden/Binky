<?php
namespace Binky;
use Bunny\Async\Client;
use Bunny\Channel;
use Bunny\Message;
use React\Promise;
use \React\EventLoop\Factory;
use React\Stream\Stream;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Command extends SymfonyCommand {
    private $colourLookup = [
        'amq.rabbitmq.log:error' => 'fg=red',
        'amq.rabbitmq.log:warning' => 'fg=yellow',
        'amq.rabbitmq.log:info' => 'fg=green'
    ];

    private $defaultBinding = ['amq.rabbitmq.log:#'];

    protected function configure() {
        $this->setName('binky')
            ->setDescription('Publish or consume RabbitMQ exchanges through stdin and stdout')
            ->addOption('bind', 'b', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'An exchange:key or exchange:header:val to bind to for consuming messages', $this->defaultBinding)
            ->addOption('pipe', 'w', InputOption::VALUE_OPTIONAL, 'Pipe streamed input to an exchange:key', null)
            ->addOption('host', 'H', InputOption::VALUE_OPTIONAL, 'Connect to HOST', '127.0.0.1')
            ->addOption('port', 'P', InputOption::VALUE_OPTIONAL, 'Connect to PORT', '5672')
            ->addOption('user', 'u', InputOption::VALUE_OPTIONAL, 'Connect using USERNAME', 'guest')
            ->addOption('pass', 'p', InputOption::VALUE_OPTIONAL, 'Connect using password PASSWORD', 'guest')
            ->addOption('vhost', 'V', InputOption::VALUE_OPTIONAL, 'Connect to vhost VHOST', '/')
            ->addOption('format', 'f', InputOption::VALUE_NONE, 'Format output all pretty like')
            ->addOption('delimiter', 'd', InputOption::VALUE_OPTIONAL, 'Delimiter to split messages apart (used with --pipe)', "\n")
            ->addOption('once', 'o', InputOption::VALUE_NONE, 'Run the script for one event and then exit (used with --pipe)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $loop = Factory::create();

        $nope = function(\Exception $e) use ($output) {
            $output->writeln("<bg=red>{$e->getMessage()}</>");
        };

        $c = new Client($loop, [
            'host'  => $input->getOption('host'),
            'port'  => (int)$input->getOption('port'),
            'user'  => $input->getOption('user'),
            'pass'  => $input->getOption('pass'),
            'vhost' => $input->getOption('vhost'),
        ]);

        $conn = $c->connect();

        $conn->then(function() use ($output) {
            $output->writeln('<bg=green>Connected...</>');
        });

        if (null !== ($destination = $input->getOption('pipe'))) {
            $conn->then(function(Client $c) {
                return $c->channel();
            })->then(function(Channel $ch) use ($loop, $destination, $input) {
                $stdin = new Stream(fopen('php://stdin', 'r+'), $loop);
                $bindings = new Bindings($destination);

                $dataParser = function($data) use ($input) {
                    return '' !== $input->getOption('delimiter') ? explode($input->getOption('delimiter'), trim($data)) : [trim($data)];
                };

                $once = $input->getOption('once') ? function() use ($ch) {
                    $ch->close()->then(function() use ($ch) {
                        $ch->getClient()->disconnect();
                    });
                } : function() {};

                $stdin->on('data', function($data) use ($ch, $bindings, $dataParser, $once) {
                    Promise\all(array_map(function($message) use ($ch, $bindings) {
                        return $ch->publish($message, [], $bindings->exchange, $bindings->routingKey);
                    }, array_filter($dataParser($data), function($message) {
                        return '' !== $message;
                    })))->then($once);
                });
            }, $nope);

            if ($this->defaultBinding === $input->getOption('bind')) {
                $input->setOption('bind', []);
            }
        }

        if ([] !== $input->getOption('bind')) {
            $conn->then(function(Client $c) {
                return $c->channel();
            })->then(function(Channel $ch) {
                return Promise\all([$ch, $ch->queueDeclare('', false, false, true, true)]);
            })->then(function($r) use ($input) {
                list($ch, $qr) = $r;

                return Promise\all(array_merge([$ch, $qr], array_map(function($exKey) use ($ch, $qr) {
                    $bindings = new Bindings($exKey);

                    return $ch->queueBind($qr->queue, $bindings->exchange, $bindings->routingKey, false, $bindings->headers);
                }, $input->getOption('bind'))));
            })->then(function($r) use ($input, $output) {
                return $r[0]->consume(function(Message $msg, Channel $ch, Client $c) use ($input, $output) {
                    $content = $msg->content;

                    if ($input->getOption('format') && ('' !== ($contentType = strtolower($msg->getHeader('content-type', ''))))) {
                        if ('application/json' === strtolower($contentType)) {
                            if ("null" !== ($pretty = json_encode(json_decode($content, true), JSON_PRETTY_PRINT))) {
                                $content = $pretty;
                            } else {
                                return $output->writeln("<bg=red>Expected JSON payload, received: {$content}</>");
                            }
                        }
                    }

                    if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                        $output->writeln(print_r($msg, true));
                    } else {
                        $cKey = "{$msg->exchange}:{$msg->routingKey}";
                        if (array_key_exists($cKey, $this->colourLookup)) {
                            $output->writeln(sprintf("<{$this->colourLookup[$cKey]}>%s</>", $content));
                        } else {
                            $output->writeln("{$cKey} > {$content}");
                        }
                    }
                }, $r[1]->queue, '', false, true, true);
            }, $nope);
        }

        try {
            $c->run();
        } catch (\Exception $e) {
            $nope($e);
        }
    }
}