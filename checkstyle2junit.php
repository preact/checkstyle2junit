<?php
/**
 * checkstyle2junit is tool for converting checkstyle xml report to junit xml report
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   checkstyle2junit
 * @author    Boris Gorbylev <ekho@ekho.name>
 * @copyright 2005-2013
 * @license   http://github.com/i-ekho/checkstyle2junit/blob/master/LICENCE LGPLv3 Licence
 * @link      http://github.com/i-ekho/checkstyle2junit
 */

if (empty($_SERVER['argv'][1])) {
    echo 'ERROR: input checkstyle xml file does not specified', PHP_EOL;
    exit(1);
}

$inFile = $_SERVER['argv'][1];

if (!file_exists($inFile)) {
    echo 'ERROR: input checkstyle xml file does not exists', PHP_EOL;
    exit(1);
}

if (!is_readable($inFile)) {
    echo 'ERROR: can not read input checkstyle xml file', PHP_EOL;
    exit(1);
}

$outFile = empty($_SERVER['argv'][2]) ? null : $_SERVER['argv'][2];

if ($outFile) {
    if (file_exists($outFile)) {
        if (!is_writeable($outFile)) {
            echo 'ERROR: out phpunit xml file exists but not writeable', PHP_EOL;
            exit(1);
        }
    } elseif (file_exists(dirname($outFile))) {
        if (!is_writeable(dirname($outFile))) {
            echo 'ERROR: directory of out phpunit xml file exists but not writeable', PHP_EOL;
            exit(1);
        }
    } else {
        echo 'ERROR: can not create out phpunit xml file exists because parent directory does not exists', PHP_EOL;
        exit(1);
    }
}

libxml_use_internal_errors(true);
$checkstyleXml = simplexml_load_file($inFile);

if ($checkstyleXml === false) {
    echo 'ERROR: Failed loading input checkstyle xml file', PHP_EOL;
    foreach (libxml_get_errors() as $error) {
        echo "\t", $error->message, PHP_EOL;
    }
    exit(1);
}

$phpunitXml = new SimpleXMLElement('<testsuites/>');

$mainSuite = $phpunitXml->addChild('testsuite');
$mainSuite->addAttribute('errors', 0);
$mainSuite->addAttribute('name', '.');
$mainSuite->addAttribute('tests', 0);
$mainSuite->addAttribute('assertions', 0);
$mainSuite->addAttribute('failures', 0);

foreach ($checkstyleXml as $file) {
    $mainSuite['tests'] += count($file);
    $mainSuite['assertions'] += count($file);

    $fileSuite = $mainSuite->addChild('testsuite');

    $failures = 0;
    foreach ($file as $error) {
        if ($error['severity'] == 'info') {
            $mainSuite['failures']--;
            continue;
        }
        
        $failures++;
        $mainSuite['failures']++;

        $case = $fileSuite->addChild('testcase');
        $case->addAttribute('name', preg_replace('@[^a-zA-Z0-9]@', ' ', $error['source']));
        $case->addAttribute('file', $file['name']);
        $case->addAttribute('line', $error['line']);
        $case->addAttribute('column', $error['column']);
        $failure = $case->addChild(
            'failure',
            $error['source'] . PHP_EOL . $error['message'] . PHP_EOL . PHP_EOL . $file['name'] . ':' . $error['line'] . ':' . $error['column']
        );
        $failure->addAttribute('type', $error['source']);
    }

    $fileSuite->addAttribute('errors', 0);
    $fileSuite->addAttribute('name', basename($file['name'], '.php'));
    $fileSuite->addAttribute('file', $file['name']);
    $fileSuite->addAttribute('tests', count($file));
    $fileSuite->addAttribute('assertions', count($file));
    $fileSuite->addAttribute('failures', $failures);
}

if ($outFile) {
    $phpunitXml->asXML($outFile);
} else {
    echo $phpunitXml->asXML();
}
