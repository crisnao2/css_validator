<?php

require '../vendor/autoload.php';

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

define('CSS_VALIDATOR_DIR', str_replace('\\', '/', realpath(dirname(__FILE__) . '/../') . '/'));

// Define the DEBUG_ENABLED constant based on the CSS_VALIDATOR_DEBUG environment variable
// If the variable is not set or invalid, disable debug by default
$debugEnv = getenv('CSS_VALIDATOR_DEBUG');
define('DEBUG_ENABLED', filter_var($debugEnv, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false);

// Enable error display for debugging
if (DEBUG_ENABLED) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Function to log messages only if DEBUG_ENABLED is true
function debugLog($message) {
    if (DEBUG_ENABLED) {
        error_log($message);
    }
}

// Function to convert SOAP output (XML) to JSON
function soapOutputToJson($soapOutput) {
    $jsonResult = [
        'cssvalidation' => [
            // 'uri' => '',
            'checkedby' => '',
            'csslevel' => '',
            'date' => '',
            'validity' => true,
            'errors' => [],
            'warnings' => [],
            'result' => [
                'errorcount' => 0,
                'warningcount' => 0
            ]
        ]
    ];

    // Parse the XML
    try {
        $xml = new SimpleXMLElement($soapOutput);
        $xml->registerXPathNamespace('env', 'http://www.w3.org/2003/05/soap-envelope');
        $xml->registerXPathNamespace('m', 'http://www.w3.org/2005/07/css-validator');

        // Extract basic information
        // $jsonResult['cssvalidation']['uri'] = (string)($xml->xpath('//m:uri')[0] ?? '');
        $jsonResult['cssvalidation']['checkedby'] = (string)($xml->xpath('//m:checkedby')[0] ?? '');
        $jsonResult['cssvalidation']['csslevel'] = (string)($xml->xpath('//m:csslevel')[0] ?? '');
        $jsonResult['cssvalidation']['date'] = (string)($xml->xpath('//m:date')[0] ?? '');
        $jsonResult['cssvalidation']['validity'] = filter_var((string)($xml->xpath('//m:validity')[0] ?? 'true'), FILTER_VALIDATE_BOOLEAN);

        // Extract error and warning counts
        $jsonResult['cssvalidation']['result']['errorcount'] = (int)($xml->xpath('//m:errorcount')[0] ?? 0);
        $jsonResult['cssvalidation']['result']['warningcount'] = (int)($xml->xpath('//m:warningcount')[0] ?? 0);

        // Extract errors
        $errors = $xml->xpath('//m:errors/m:errorlist/m:error');

        debugLog("Number of errors found in XML: " . count($errors));

        foreach ($errors as $error) {
            $source = (string)($error->xpath('../m:uri')[0] ?? '');
            $line = (int)($error->xpath('m:line')[0] ?? 0);
            $context = (string)($error->xpath('m:context')[0] ?? '');
            $type = (string)($error->xpath('m:type')[0] ?? '');
            $errortype = (string)($error->xpath('m:errortype')[0] ?? '');
            $errorsubtype = (string)($error->xpath('m:errorsubtype')[0] ?? '');
            $skippedstring = (string)($error->xpath('m:skippedstring')[0] ?? '');
            $message = trim((string)($error->xpath('m:message')[0] ?? ''));

            debugLog("Extracted error - source: $source, line: $line, context: $context, type: $type, skippedstring: $skippedstring, message: $message");

            $errorData = [
                // 'source' => $source,
                'line' => $line,
                'context' => $context,
                'type' => $type,
                'errortype' => $errortype,
                'errorsubtype' => $errorsubtype,
                // 'skippedstring' => $skippedstring,
                'message' => $message
            ];

           // Remove empty keys
            $errorData = array_filter($errorData, function($value) {
                return $value !== '' && $value !== 0;
            });

            $jsonResult['cssvalidation']['errors'][] = $errorData;
        }

        // Extract warnings
        $warnings = $xml->xpath('//m:warnings/m:warninglist/m:warning');

        debugLog("Number of warnings found in XML: " . count($warnings));

        foreach ($warnings as $warning) {
            $source = (string)($warning->xpath('../m:uri')[0] ?? '');
            $line = (int)($warning->xpath('m:line')[0] ?? 0);
            $context = (string)($warning->xpath('m:context')[0] ?? '');
            $message = trim(((string)$warning->xpath('m:message')[0] ?? ''));

            debugLog("Extracted warning - source: $source, line: $line, context: $context, message: $message");

            $warningData = [
                // 'source' => $source,
                'line' => $line,
                'context' => $context,
                'type' => 'warning',
                'message' => $message
            ];

            $jsonResult['cssvalidation']['warnings'][] = $warningData;
        }
    } catch (Exception $ex) {
        debugLog("Error parsing XML: " . $ex->getMessage());
        return [
            'error' => 'Error processing SOAP output',
            'details' => $ex->getMessage()
        ];
    }

    return $jsonResult;
}

// Simple backend to validate CSS with configurable options
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['css'])) {
    $css = trim(html_entity_decode($_POST['css']));

    // Remove leading and trailing quotes from the CSS string
    $css = trim($css, "'\"");

    // Allowed parameters: only profile and lang
    $params = [
        'profile' => $_POST['profile'] ?? 'css3svg',
        'lang' => $_POST['lang'] ?? 'en'
    ];

    // Fixed default values for other parameters
    $defaultParams = [
        'medium' => 'all',
        'output' => 'soap12',
        'warning' => '2',
        'vextwarning' => true,
        'printCSS' => false
    ];

    // Validation of allowed parameters
    $validProfiles = ['css1', 'css2', 'css21', 'css3', 'css3svg', 'svg', 'svgbasic', 'svgtiny', 'atsc-tv', 'mobile', 'tv'];
    $validLangs = ['bg', 'cs', 'de', 'el', 'en', 'es', 'fa', 'fr', 'hi', 'hu', 'it', 'ja', 'ko', 'nl', 'pl-PL', 'pt-BR', 'ro', 'ru', 'sv', 'uk', 'zh-cn'];

    if (!in_array($params['profile'], $validProfiles)) {
        http_response_code(400);
        echo json_encode(['error' => "Invalid profile. Valid values: " . implode(', ', $validProfiles)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!in_array($params['lang'], $validLangs)) {
        http_response_code(400);
        echo json_encode(['error' => "Invalid lang. Valid values: " . implode(', ', $validLangs)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Build the command with fixed default values
    $command = ['java', '-jar', CSS_VALIDATOR_DIR . 'css-validator.jar'];
    $command[] = '--profile=' . $params['profile'];
    $command[] = '--medium=' . $defaultParams['medium'];
    $command[] = '--output=' . $defaultParams['output'];
    $command[] = '--lang=' . $params['lang'];
    $command[] = '--warning=' . $defaultParams['warning'];

    if ($defaultParams['vextwarning']) {
        $command[] = '--vextwarning=true';
    }

    if ($defaultParams['printCSS']) {
        $command[] = '--printCSS';
    }

    // Create a temporary file with .css extension
    $inputFile = tempnam(sys_get_temp_dir(), 'css_') . '.css';

    file_put_contents($inputFile, $css);

    $fileUri = 'file://' . $inputFile;
    $command[] = $fileUri;

    // Log the command and file content for debugging
    debugLog("Executed command: " . implode(' ', $command));
    debugLog("Temporary file content: " . $css);

    // Create the process
    $process = new Process($command);
    $process->setTimeout(10); // 10 seconds timeout

    try {
        $process->run();

        // Capture output and errors
        $output = $process->getOutput();
        $errors = $process->getErrorOutput();

        // Remove the temporary file
        unlink($inputFile);

        // Log the output for debugging
        debugLog("Output: " . ($output ?: "No output"));
        debugLog("Errors: " . ($errors ?: "No errors"));
        debugLog("Return code: " . $process->getExitCode());

        // Convert SOAP output (XML) to JSON
        $jsonOutput = soapOutputToJson($output);

        // Always return JSON
        header('Content-Type: application/json');

        if ($output !== '' && !isset($jsonOutput['error'])) {
            echo json_encode($jsonOutput, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'error' => 'Error validating CSS',
                'details' => $errors ?: ($jsonOutput['details'] ?? 'No output or error captured'),
                'exit_code' => $process->getExitCode()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    } catch (ProcessFailedException $ex) {
        // Remove the temporary file in case of error
        if (file_exists($inputFile)) {
            unlink($inputFile);
        }

        debugLog("Process error: " . $ex->getMessage());
        http_response_code(500);

        echo json_encode([
            'error' => 'Failed to execute validator',
            'details' => $ex->getMessage(),
            'exit_code' => $process->getExitCode()
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Send CSS via POST in the "css" field with optional parameters'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
