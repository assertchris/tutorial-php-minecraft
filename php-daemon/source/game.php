<?php

class Game
{
    private $builder;
    private $shopware;
    private $filesystem;

    private $log = "/Users/assertchris/Desktop/shopware/minecraft-server/logs/latest.log";

    private $grid = [
        [-337, 64, 444], [-336, 64, 444], [-335, 64, 444], [-334, 64, 444], [-333, 64, 444],
        [-337, 64, 445], [-336, 64, 445], [-335, 64, 445], [-334, 64, 445], [-333, 64, 445],
        [-337, 64, 446], [-336, 64, 446], [-335, 64, 446], [-334, 64, 446], [-333, 64, 446],
        [-337, 64, 447], [-336, 64, 447], [-335, 64, 447], [-334, 64, 447], [-333, 64, 447],
        [-337, 64, 448], [-336, 64, 448], [-335, 64, 448], [-334, 64, 448], [-333, 64, 448],
    ];

    private $products = [
        [-347, 65, 516],
        [-349, 65, 516],
        [-349, 65, 513],
        [-347, 65, 513],
    ];

    private $users = [];
    private $timestamp = null;

    public function __construct($builder, $shopware, $filesystem)
    {
        $this->builder = $builder;
        $this->shopware = $shopware;
        $this->filesystem = $filesystem;
    }

    public function run()
    {
        $this->builder->exec("/blockdata -335 65 445 {
            Text2: \"{\\\"text\\\": \\\"Step into\\\"}\",
            Text3: \"{\\\"text\\\": \\\"the grid...\\\"}\"
        }");

        $this->builder->exec("/setblock -344 64 515 iron_bars");
        $this->builder->exec("/setblock -344 65 515 iron_bars");
        $this->builder->exec("/setblock -344 64 514 iron_bars");
        $this->builder->exec("/setblock -344 65 514 iron_bars");

        foreach ($this->products as $coordinates) {
            [ $x, $y, $z ] = $coordinates;

            $this->builder->exec("/blockdata {$x} {$y} {$z} {
                Text2: \"{\\\"text\\\": \\\" \\\"}\",
                Text3: \"{\\\"text\\\": \\\" \\\"}\"
            }");
        }

        Amp\run(function () {
            Amp\repeat(function () {
                /* yield from */ $this->repeatGridSearch();
            }, 1000 * 1 * 1);

            $this->timestamp = yield $this->filesystem->mtime($this->log);

            Amp\repeat(function () {
                yield from $this->repeatLogWatch();
            }, 500);
        });
    }

    private function repeatGridSearch()
    {
        foreach ($this->grid as $coordinates) {
            [ $x, $y, $z ] = $coordinates;

            $user = $this->findAt($x, $y, $z);

            if ($user) {
                return $this->builder->exec("/blockdata -335 65 445 {
                    Text2: \"{\\\"text\\\": \\\"{$user}\\\"}\",
                    Text3: \"{\\\"text\\\": \\\"{$x} {$y} {$z}\\\"}\"
                }");
            }
        }
    }

    private function findAt($x, $y, $z)
    {
        $found = $this->builder->exec("/testfor @a[x={$x},y={$y},z={$z},r=1]");

        preg_match("/Found (\w+)/", $found, $matches);

        if (count($matches) > 0) {
            return $matches[1];
        }

        return null;
    }

    private function repeatLogWatch()
    {
        $contents = yield from $this->getContents($this->timestamp);

        if (!empty($contents)) {
            $lines = array_reverse(explode(PHP_EOL, $contents));

            foreach ($lines as $line) {
                $isCommand = stristr($line, "> !") !== false;

                if ($isCommand) {
                    return $this->executeCommand($line);
                }
            }
        }
    }

    private function getContents($then)
    {
        $now = yield $this->filesystem->mtime($this->log);
    
        if ((string) $then !== (string) $now) {
            $previous = $now;
    
            $contents = yield $this->filesystem->get($this->log);
            
            // overwrite the file contents, so we don't get repeated commands...
            yield $this->filesystem->put($this->log, "");

            return $contents;
        }
    
        return null;
    }

    private function executeCommand($raw)
    {
        preg_match("/<(\w+)>/", $raw, $matches);
    
        $user = null;
    
        if (count($matches) > 0) {
            $user = $matches[1];
        }
    
        $command = trim(
            substr($raw, stripos($raw, "> !") + 3)
        );
    
        if (stripos($command, "ping") === 0) {
            $this->builder->exec("/say Pong...");
        }
    
        if (stripos($command, "shop") === 0) {
            if (!isset($this->users[$user])) {
                return $this->builder->exec("/say You need to tell us your email first. Type '!email [your email]'");
            }
    
            $this->builder->exec("/setblock -344 64 515 air");
            $this->builder->exec("/setblock -344 65 515 air");
            $this->builder->exec("/setblock -344 64 514 air");
            $this->builder->exec("/setblock -344 65 514 air");

            Amp\once(function () {
                $this->builder->exec("/setblock -344 64 515 iron_bars");
                $this->builder->exec("/setblock -344 65 515 iron_bars");
                $this->builder->exec("/setblock -344 64 514 iron_bars");
                $this->builder->exec("/setblock -344 65 514 iron_bars");
            }, 3000);
        }
    
        if (stripos($command, "email") === 0) {
            $parts = explode(" ", $command);

            $results = $this->shopware->get("users", [
                "filter" => [
                    [
                        "property" => "email",
                        "expression" => "=",
                        "value" => $parts[1],
                    ],
                ],
            ]);

            if (!$results->success || count($results->data) < 1) {
                return $this->builder->exec("/say Email not found. Try again");
            }

            $this->builder->exec("/say You're good to go!");
        
            $this->users[$user] = $parts[1];
        }
    
        if (stripos($command, "refresh") === 0) {
            $results = $this->shopware->get("articles", [
                "limit" => 4,
                "sort" => [
                    [
                        "property" => "changed",
                        "direction" => "DESC",
                    ],
                ],
            ]);

            if ($results->success) {
                foreach ($results->data as $i => $article) {
                    [ $x, $y, $z ] = $this->products[$i];

                    $details = $this->shopware->get("articles/{$article->id}");

                    $name = $article->name;
                    $price = number_format($details->data->mainDetail->prices[0]->price, 2);
                    
                    $this->builder->exec("/blockdata {$x} {$y} {$z} {
                        Text2: \"{\\\"text\\\": \\\"{$name}\\\"}\",
                        Text3: \"{\\\"text\\\": \\\"\${$price}\\\"}\"
                    }");
                }
            }
        }
    }
}
