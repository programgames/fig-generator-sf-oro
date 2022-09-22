<?php

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

function getSymfonyCommands($application)
{
    $commands = getSymfonyCommandList($application);

    $symfonyCommands = "";
    foreach ($commands as $key => $command) {
        $description = trim(str_replace(['"'], ['', '\\"'], substr($command, 77)));
        $commandNames = trim(substr($command, 0, 77));
        $commandNames = explode(',',$commandNames);
        $commandName = $commandNames[0];
        $process = new Process(['symfony', $commandName, '--help']);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = $process->getOutput();
        $commandArray = explode(PHP_EOL, $output);
        $commandArray = array_map(function ($item) {
            $item = trim($item);
            return preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $item);
        }, $commandArray);
        $commandArray = array_filter($commandArray);


        $commandText = buildCommandText($commandArray, $commandName, $description);
        $symfonyCommands .= PHP_EOL . $commandText;
    }
    return $symfonyCommands . PHP_EOL . '      ';
}

function getSymfonyCommandList($application)
{
    $process = new Process(['symfony', '--help']);
    $process->run();

    if (!$process->isSuccessful()) {
        throw new ProcessFailedException($process);
    }

    $commands = $process->getOutput();

    $commandArray = explode(PHP_EOL, $commands);
    $commandArray = array_map(function ($item) {
        return preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $item);
    }, $commandArray);
    $commands = array_filter($commandArray, static function ($comm) {
        return preg_match("/^(?!  -) .*:.* [0-9A-Za-z]+/", $comm) || preg_match("/^(?!  -) .*:.*, .*/", $comm);
    }
    );

    return $commands;
}

function buildCommandText($commandParts, $commandName, $description)
{
    $argFound = false;
    $optionsFound = false;
    $rawOptions = [];
    $rawArguments = [];

    foreach ($commandParts as $commandPart) {
        if ($commandPart === 'Options:') {
            $optionsFound = true;
            $argFound = false;
        } elseif ($commandPart === 'Arguments:') {
            $argFound = true;
            $optionsFound = false;
        } elseif ($optionsFound && $commandPart !== 'Arguments:') {
            $rawOptions[] = $commandPart;
        } elseif ($argFound && $commandPart !== 'Options:') {
            $rawArguments[] = $commandPart;
        }
    }

    if (empty($rawArguments)) {
        $args = '[]';
    } else {
        $args = "[";
        foreach ($rawArguments as $rawArgument) {
            $argName = strtok($rawArgument, " ");
            $argDescription = str_replace('"', '\\"', trim(substr($rawArgument, strlen($argName))));
            $isOptionnal = str_contains('(required)', $rawArgument) ? 'true' : 'false';
            $arg = <<<TEXT
                {
                        name: "$argName",
                        description: "$argDescription",
                        isOptional: $isOptionnal,
                },
TEXT;
            $args .= PHP_EOL . $arg;
        }
        $args .= PHP_EOL . "]";
    }

    return <<<TEXT
            {
              name: "$commandName",
              description: "$description",
              args: $args
            },
TEXT;
}
