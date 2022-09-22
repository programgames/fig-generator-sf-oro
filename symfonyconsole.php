<?php

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

function getSymfonyConsoleCommands($application)
{
    $process = new Process(['bin/console', 'list', '--raw']);
    $process->run();

    if (!$process->isSuccessful()) {
        throw new ProcessFailedException($process);
    }

    $commands = $process->getOutput();

    $commandArray = explode(PHP_EOL, $commands);
    $commands = array_filter($commandArray, static function ($comm) {
        if (preg_match('/^[a-z]+.*:[a-z]+.*/', $comm)) {
            return true;
        }
    }
    );
    $symfonyConsoleCommands = "";
    foreach ($commands as $command) {
        $comm = explode(' ', $command, 2);
        $commandSF = $application->get($comm[0]);
        $definition = $commandSF->getDefinition();
        $arguments = $definition->getArguments();
        $options = $definition->getOptions();

        $optionsFormatted = "";

        /** @var \Symfony\Component\Console\Input\InputOption $option */
        foreach ($options as $option) {
            $name = strlen($option->getName()) > 1 ? "--" . $option->getName() : "-" . $option->getName();
            $shortcut = null;
            if ($option->getShortcut()) {
                $shortcut = strlen($option->getShortcut()) > 1 ? "--" . $option->getShortcut() : "-" . $option->getShortcut();
            }
            if ($shortcut) {
                $name = "\"" . $name . "\"," . "\"" . $shortcut . "\"";
            } else {
                $name = "\"" . $name . "\"";
            }

            $desc = str_replace(array("\"", PHP_EOL), array("\\\"", " "), $option->getDescription());
            $optionText = <<<TEXT
        {
          name: [$name],
          description: "$desc",
        },
TEXT;
            $optionsFormatted .= $optionText;
        }
        $optionsFormatted = addDefaultOptions($optionsFormatted);
        $argumentFormatteds = "";

        foreach ($arguments as $argument) {

            $name = $argument->getName();
            $isOptional = $argument->isRequired() ? 'false' : 'true';
            $desc = str_replace(array("\"", PHP_EOL), array("\\\"", " "), $argument->getDescription());
            $argumentText = <<<TEXT
        {
          name: "$name",
          description: "$desc",
          isOptional: $isOptional,
         }, 

TEXT;
            $argumentFormatteds .= $argumentText;
        }

        $desc = str_replace("\"", " \\", trim($comm[1]));
        $text = <<<JS
{
            name: "$comm[0]",
            description: "$desc",
            args : [
            $argumentFormatteds
            ],
            options: [
            $optionsFormatted
            ]
},

JS;

        $symfonyConsoleCommands .= $text;
    }
    return $symfonyConsoleCommands;
}

function addDefaultOptions(string $optionsFormatted)
{
    $defaultOptions = [
        [
            'names' => ['--help', '-h'],
            'description' => 'Display this help message'
        ],
        [
            'names' => ['--quiet', '-q'],
            'description' => 'Do not output any message'
        ],
        [
            'names' => ['--version', '-V'],
            'description' => 'Display this application version'
        ],
        [
            'names' => ['--ansi'],
            'description' => 'Force ANSI output'
        ],
        [
            'names' => ['--no-ansi'],
            'description' => 'Disable ANSI output'
        ],
        [
            'names' => ['--no-interaction', '-n'],
            'description' => 'Do not ask any interactive question'
        ],
        [
            'names' => ['--env', '-e'],
            'description' => 'The Environment name. [default: "dev"]'
        ],
        [
            'names' => ['--no-debug'],
            'description' => 'Switches off debug mode'
        ],
        [
            'names' => ['--disabled-listeners'],
            'description' => '"all" or run oro:platform:optional-listeners to see available (multiple values allowed)'
        ],
        [
            'names' => ['--current-user'],
            'description' => ' ID, username or email'
        ],
        [
            'names' => ['--current-organization'],
            'description' => 'ID or organization name (required if user has access to multiple organizations)'
        ],
        [
            'names' => ['-v|vv|vvv', '--verbose'],
            'description' => 'Verbosity level'
        ],
    ];

    foreach ($defaultOptions as $defaultOption) {
        $commandName = "";
        foreach ($defaultOption['names'] as $name) {
            $commandName .= "\"$name\",";
        }
        $commandName = substr_replace($commandName ,"",-1);
        $desc =  str_replace(array("\"", PHP_EOL), array("\\\"", " "), $defaultOption['description']);
        $optionText = <<<TEXT
        {
          name: [$commandName],
          description: "$desc",
        },
TEXT;
        $optionsFormatted .= $optionText;

    }


    return $optionsFormatted;
}
