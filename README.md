# Binky

Binky is a command line application to publish or consume RabbitMQ exchanges through stdin and stdout

### Installation

    composer global require cboden/binky

### Usage

    binky --help

Consume all messages from a topic or fanout exchange:

    binky -b amq.topic

Consume all errors and warnings from RabbitMQ's log exchange via routing keys error+warning:

    binky -b amq.rabbitmq.log:error -b amq.rabbitmq.log:warning

Consume all messages on a header exchange with key "header" and value "value"

    binky -b amq.headers:header:value

Consume all messages on a header exchange where all key/val properties have to match:

    binky -b "amq.headers:header1:value1&header2:value2"

Consume all messages on a header exchange where any key/val properties can match:

    binky -b "amq.headers:header1:value1|header2:value2"

Consume all messages from an existing queue (does not re-queue):

    binky -c "my-queue" -c "another-queue"

Publish to an exchange with routing key when ever a file is appended to:

    tail -0f /var/log/nginx/error.log | binky -w amq.topic:errors.nginx

Publish the entire contents of a file to an exchange and disconnect (with messages delimited by new lines):

    cat myScaffoldingFile | binky -w amq.topic:myKey -o

### Whoops

If you see the error `Broken pipe or closed connection` Binky had trouble connecting to the broker while displaying sub-par error reporting.
If you tried to run Binky without any params most likely the default guest user is disabled or the defaults don't match; try again with some auth:

    binky -u admin -p admin

### Why Binky?

I was doing so much debugging in RabbitMQ that the methods I was using were becoming too time consuming.
The admin panel was cumbersome, consuming via WebSocket was good but the browser console became monotonous, and finally making and editing little scripts became tedious.

I decided to look for a binary online but didn't find what I was looking for after 20 minutes of searching: a simple CLI script to bind to and consume messages from an exchange then writing them to STDOUT.

Secondly, I wanted to try out [Bunny](https://github.com/jakubkulhan/bunny), an alternative to the C extension and the more popular php-amqplib, as it offered both a synchronous and asynchronous implementation with the same interfaces.

An hour of work later and Binky was born. [Bunny](https://github.com/jakubkulhan/bunny) kicked ass!

### What's a Binky?

> An expression of joy from a rabbit. When a rabbit binkies, it jumps into the air, often twisting and flicking its feet and head.

They're weird creatures...