<?php

require __DIR__ . "/vendor/autoload.php";

use Carica\Io;
use Carica\Firmata;

$board = new Firmata\Board(
    Io\Stream\Serial\Factory::create("/dev/cu.usbmodem1451", 57600)
);

$loop = Io\Event\Loop\Factory::get();

print "connecting" . PHP_EOL;

$board
    ->activate()
    ->done(function () use ($board, $loop) {
        print "connected" . PHP_EOL;

        $pin = $board->pins[13];
        $pin->mode = Firmata\Pin::MODE_OUTPUT;

        $previous = null;

        $loop->setInterval(function () use ($pin, &$previous) {
            $path = "/Users/assertchris/Library/Application Support/minecraft/logs/latest.log";

            $content = file_get_contents($path);
            $lines = explode("\n", trim($content));
            $last = array_pop($lines);

            if (stristr($last, "[@] near")) {
                print "turn on" . PHP_EOL;
                $pin->digital = 1;
            }

            if (stristr($last, "[@] far")) {
                print "turn off" . PHP_EOL;
                $pin->digital = 0;
            }

            file_put_contents($path, "");
        }, 500);
    })
    ->fail(function ($error) {
        print "error: {$error}" . PHP_EOL;
    });

$loop->run();
