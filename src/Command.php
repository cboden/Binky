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

    protected function configure() {
        $this->setName('binky')
            ->setDescription('Introspect RabbitMQ exchanges')
            ->addOption('bind', 'b', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'an exchange:key to bind to', ['amq.rabbitmq.log:#'])
            ->addOption('host', 'H', InputOption::VALUE_OPTIONAL, 'host to connect to', '127.0.0.1')
            ->addOption('port', 'P', InputOption::VALUE_OPTIONAL, 'Port to bind to', '5672')
            ->addOption('user', 'u', InputOption::VALUE_OPTIONAL, 'username to connect to as', 'guest')
            ->addOption('pass', 'p', InputOption::VALUE_OPTIONAL, 'password to connect to for given user', 'guest')
            ->addOption('vhost', 'vh', InputOption::VALUE_OPTIONAL, 'virtual host to create channel on', '/')
            ->addOption('format', 'f', InputOption::VALUE_NONE, 'format output all pretty like')
            ->addOption('pipe', 'w', InputOption::VALUE_OPTIONAL, 'pipe input to an exchange:key', null)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $loop = Factory::create();

        $c = new Client($loop, [
            'host'  => $input->getOption('host'),
            'port'  => (int)$input->getOption('port'),
            'user'  => $input->getOption('user'),
            'pass'  => $input->getOption('pass'),
            'vhost' => $input->getOption('vhost'),
        ]);

        $conn = $c->connect();

        if (null !== ($destination = $input->getOption('pipe'))) {
            $conn->then(function(Client $c) {
                return $c->channel();
            })->then(function(Channel $ch) use ($loop, $destination) {
                $stdin = new Stream(fopen('php://stdin', 'r+'), $loop);
                $bindings = new Bindings($destination);

                $stdin->on('data', function($data) use ($ch, $bindings) {
                    $ch->publish($data, [], $bindings->exchange, $bindings->routingKey);
                });
            });
        }

        if (null !== $input->getOption('bind')) {
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

                    $cKey = "{$msg->exchange}:{$msg->routingKey}";
                    if (array_key_exists($cKey, $this->colourLookup)) {
                        $output->writeln(sprintf("<{$this->colourLookup[$cKey]}>%s</>", $content));
                    } else {
                        $output->writeln("{$cKey} > {$content}");
                    }
                }, $r[1]->queue, '', false, true, true);
            });
        }

        $c->run();
    }
}