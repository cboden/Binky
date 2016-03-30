# Binky

Binky is a command line application to introspect RabbitMQ exchanges and output the messages to STDOUT

### Installation

    composer global require cboden/binky

### Usage

    binky --help

More docs will come soon. If you run binky without any arguments it will attempt to connect to localhost via the guest user and consume the RabbitMQ log.

### Why Binky?

I was doing so much debugging in RabbitMQ that the methods I was using were becoming too time consuming.
The admin panel was cumbersome, consuming via WebSocket was good but the browser console became monotonous, and finally making and editing little scripts became tedious.

I decided to look for a binary online but didn't find what I was looking for after 20 minutes of searching: a simple CLI script to bind to and consume messages from an exchange then writing them to STDOUT.

Secondly, I wanted to try out [Bunny](https://github.com/jakubkulhan/bunny), an alternative to the C extension and the more popular php-amqplib, as it offered both a synchronous and asynchronous implementation with the same interfaces.

An hour of work later and Binky was born. [Bunny](https://github.com/jakubkulhan/bunny) kicked ass!

### What's a Binky?

> An expression of joy from a rabbit. When a rabbit binkies, it jumps into the air, often twisting and flicking its feet and head.

They're weird creatures...