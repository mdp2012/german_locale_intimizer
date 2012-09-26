#!/bin/php
<?php

if (count($argv) < 1) {
    die ("please provide a translation file or a folder containing these");
}
$sourceFilePath = $argv[1];

$converter = new Converter();
if (is_file($sourceFilePath)) {
    $files = array($sourceFilePath);
} elseif (is_dir($sourceFilePath)) {
    $files = glob($sourceFilePath . '*.csv');
}
foreach ($files as $sourceFile) {
    $converter->convert($sourceFile);
}

class Converter
{
    protected $knownChanges = array();
    protected $tokensToReplace = array('Sie ', 'Ihr');

    public function convert($sourceFile)
    {
        $targetFilePath = str_replace('de_DE', 'de_DE_1', $sourceFile);
        $this->loadKnownChanges();
        $file = fopen($sourceFile, 'r');
        $targetFile = fopen($targetFilePath, 'a');
        $occurences = 0;
        $saveBufferCount = 0;
        while ($line = fgetcsv($file)) {
            $source = current($line);
            $originalTranslation = end($line);
            if ($this->containsTokenToReplace($originalTranslation)) {
                ++$occurences;
                ++$saveBufferCount;
                $target = $this->getChangedString($originalTranslation);
                fputcsv($targetFile, array($source, $target));
                if (10 < $saveBufferCount) {
                    $saveBufferCount = 0;
                    $this->saveKnownChanges();
                }
            }
        }
        echo "$occurences occurences in $sourceFile" . PHP_EOL;
        $this->saveKnownChanges();
    }

    protected function containsTokenToReplace($string)
    {
        foreach ($this->tokensToReplace as $token) {
            if (false !== strpos($string, $token)) {
                return true;
            }
        }
        return false;
    }

    protected function loadKnownChanges()
    {
        if (file_exists($this->getKnownChangesPath())) {
            $file = fopen($this->getKnownChangesPath(), 'r');
            while ($line = fgetcsv($file)) {
                $this->knownChanges[$line[0]] = $line[1];
            }
            fclose($file);
        }
    }

    protected function getKnownChangesPath()
    {
        return dirname(__FILE__) . '/data/knownChanges.csv';
    }

    /**
     * get changed string
     * 
     * @param string $string 
     * @param int    $ignore Ignore that count of occurences
     * @return string
     */
    protected function getChangedString($string, $ignore=0)
    {
        $orig = $string;
        foreach ($this->knownChanges as $from=>$to) {
            $p = $string;
            $string = preg_replace("~$from~", $to, $string, -1, $count);
            if ($this->containsTokenToReplace($to)) {
                $ignore++;
            }
        }
        if (false !== $this->containsTokenToReplace($string)
            && 0 == $ignore
        ) {
            $this->getUserChanges($string);
            $string = $this->getChangedString($string, $ignore);
        }
        return $string;
    }

    protected function saveKnownChanges()
    {
        $file = fopen($this->getKnownChangesPath(), 'w');
        foreach ($this->knownChanges as $from=>$to) {
            fputcsv($file, array($from, $to));
        }
        fclose($file);
    }

    protected function getUserChanges($original)
    {
        echo "===> $original\n";
        echo "Please enter the parts to be changed:\n";
        $snippets = $this->getInput();
        echo "Please enter matching \"du\" version(s):\n";
        $changes = $this->getInput();
        if (count($snippets) != count($changes)) {
            echo "count does not match\n";
            return $this->getUserChanges($original);
        }
        echo "- added " . count($snippets) . " changes\n";
        foreach ($snippets as $key=>$snippet) {
            $this->knownChanges[trim($snippet)] = trim($changes[$key]);
        }
    }

    protected function getInput()
    {
        $fp = fopen('php://stdin', 'r');
        $last_line = false;
        $strings = array();
        while (!$last_line) {
            $next_line = fgets($fp, 1024); // read the special file to get the user input from keyboard
            if ("\n" == $next_line) {
                $last_line = true;
            } else {
                $strings[] = $next_line;
            }
        }
        return $strings;
    }

    protected function getKnownChange($original)
    {
        if (array_key_exists($original, $this->knownChanges)) {
            return $this->knownChanges[$original];
        }
    }
}
