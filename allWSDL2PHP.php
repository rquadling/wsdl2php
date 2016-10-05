<?php

/**
 * Generate all the WSDL based PHP classes from the available WSDL files/URLs.
 */
preg_match_all(
    '`^[^#]*?\s++(?P<WSDLURLs>[^\s]++)$`sim',
    file_get_contents(__DIR__.'/WSDLs/wsdl.txt'),
    $a_Matches,
    PREG_PATTERN_ORDER
);
$a_WSDLs = $a_Matches['WSDLURLs'];

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/WSDLs')) as $o_File) {
    if ($o_File->isFile() && 'wsdl' == strtolower($o_File->getExtension())) {
        $a_WSDLs[] = $o_File->getPathname();
    }
}

@mkdir(__DIR__.'/WSDLs/logs', 0755, true);

foreach ($a_WSDLs as $s_WSDL) {
    echo 'About to process ', $s_WSDL, PHP_EOL;
    exec(
        "php ./wsdl2php.php '{$s_WSDL}'>./WSDLs/logs/".preg_replace(
            '`[^\w]`',
            '_',
            is_file($s_WSDL) ? substr($s_WSDL, 1 + strlen(__DIR__.'/WSDLs')) : $s_WSDL
        ).'.log'
    );
}
