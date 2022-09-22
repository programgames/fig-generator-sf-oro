<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Debug\Debug;

require('symfonyconsole.php');
require ('symfonycommand.php');
require __DIR__ . '/../vendor/autoload.php';

$input = new ArgvInput();
$env = $input->getParameterOption(array('--env', '-e'), getenv('SYMFONY_ENV') ?: 'dev');
if ($env === 'dev' && !isset($_ENV['APP_FRONT_CONTROLLER'])) {
    $_ENV['APP_FRONT_CONTROLLER'] = 'index_dev.php';
}
$debug = getenv('SYMFONY_DEBUG') !== '0' && !$input->hasParameterOption(array('--no-debug', '')) && $env !== 'prod';

if ($debug) {
    Debug::enable();
}

$kernel = new AppKernel($env, $debug);
$application = new Application($kernel);


$symfonyCommands = getSymfonyCommands($application);
$symfonyConsoleCommands = getSymfonyConsoleCommands($application);


$js = <<<JS
const completionSpec: Fig.Spec = {
    name: "symfony",
    description: "Symfony cli",
    subcommands:
        [
            $symfonyCommands
            {
              name: "console",
              description: "Symfony console wrapper",
              subcommands:
              [
              $symfonyConsoleCommands
              ]
            }
        ],
    options: [
        {
            name: ["--help", 'h'],
            description: "Show help",
        },
        {
            name: ["--no-ansi"],
            description: "Disable ANSI output",
        },
        {
            name: ["--ansi"],
            description: "Force ANSI output",
        },
        {
            name: ["--no-interaction "],
            description: "Disable all interactions",
        },
        {
            name: ["--quiet", '-q'],
            description: "Do not output any message",
        },
        {
            name: ["-v", '-vv','-vvv','--verbose', '--log-level'],
            description: "Increase the verbosity of messages: 1 for normal output, 2 and 3 for more verbose outputs and 4 for debug",
        },
        {
            name: ["V"],
            description: "Print the version",
        },
    ],
};

export default completionSpec;

JS;

file_put_contents('symfony.ts', $js);

function str_contains($haystack, $needle) {
    return $needle !== '' && mb_strpos($haystack, $needle) !== false;
}
