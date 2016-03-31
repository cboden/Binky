<?php
namespace Binky;

class Bindings {
    public $exchange   = '';
    public $routingKey = '';
    public $headers    = [];

    public function __construct($bindingString) {
        $bindings = explode(':', $bindingString);
        $this->exchange = array_shift($bindings);

        switch (count($bindings)) {
            case 0: // assuming topic or fanout exchange
                $this->routingKey = '#';
                break;
            case 1: // assuming topic exchange
                $this->routingKey = $bindings[0];
                break;
            default: // assuming header exchange
                if (strstr($bindingString, '&')) {
                    $this->headers = $this->parseHeader('&', 'all', $bindingString);
                } else if (strstr($bindingString, '|')) {
                    $this->headers = $this->parseHeader('|', 'any', $bindingString);
                } else {
                    list($key, $val) = $bindings;
                    $this->headers[$key] = $val;
                }

                break;
        }
    }

    private function parseHeader($delimiter, $match, $bindingString) {
        return array_reduce(explode($delimiter, substr($bindingString, strlen($this->exchange) + 1)), function($acc, $string) {
            list($key, $val) = explode(':', $string);
            $acc[$key] = $val;

            return $acc;
        }, ['x-match' => $match]);

    }
}