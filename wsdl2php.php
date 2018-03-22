<?php

// +------------------------------------------------------------------------+
// | wsdl2php                                                               |
// +------------------------------------------------------------------------+
// | Copyright (C) 2005 Knut Urdalen <knut.urdalen@gmail.com>               |
// +------------------------------------------------------------------------+
// | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS    |
// | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT      |
// | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR  |
// | A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT   |
// | OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,  |
// | SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT       |
// | LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,  |
// | DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY  |
// | THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT    |
// | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE  |
// | OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.   |
// +------------------------------------------------------------------------+
// | This software is licensed under the LGPL license. For more information |
// | see http://wsdl2php.sf.net                                             |
// +------------------------------------------------------------------------+

ini_set('soap.wsdl_cache_enabled', 0); // disable WSDL cache

if ($_SERVER['argc'] != 2) {
    die("usage: wsdl2php <wsdl-file>\n");
}

$wsdl = $_SERVER['argv'][1];

echo 'Analyzing WSDL of ', $wsdl, PHP_EOL;
$s_Processed = date('r');

try {
    $client = new SoapClient(
        $wsdl, [
            'encoding' => 'UTF-8',
            'exception' => true,
            'trace' => true,
            'user_agent' => 'PHP',
        ]
    );
} catch (SoapFault $e) {
    echo
        // Show exception.
    'Exception', PHP_EOL,
    '---------', PHP_EOL,
    $e->getMessage(), PHP_EOL;
} catch (Exception $e) {
    // As we are going to need to display XML, use Tidy to make it pretty.
    $a_TidyConfig = [
        'indent' => true,
        'indent-spaces' => 4,
        'indent-attributes' => true,
        'input-xml' => true,
        'output-xml' => true,
        'wrap' => 0,
    ];
    $o_Tidy = new Tidy();

    echo
        // Show trace.
    PHP_EOL,
    'Request Headers', PHP_EOL,
    '---------------', PHP_EOL,
    $client->__getLastRequestHeaders(), PHP_EOL,
    'Request', PHP_EOL,
    '-------', PHP_EOL,
    $o_Tidy->repairString($client->__getLastRequest(), $a_TidyConfig), PHP_EOL, PHP_EOL,
    'Response Headers', PHP_EOL,
    '----------------', PHP_EOL,
    $client->__getLastResponseHeaders(), PHP_EOL,
    'Response', PHP_EOL,
    '--------', PHP_EOL,
    $o_Tidy->repairString($client->__getLastResponse(), $a_TidyConfig), PHP_EOL, PHP_EOL, PHP_EOL,
        // Show exception.
    'Exception', PHP_EOL,
    '---------', PHP_EOL,
    $e->getMessage(), PHP_EOL;
    exit;
}

echo 'Loaded WSDL', PHP_EOL;

$dom = new DOMDocument();
$dom->load($wsdl);
$xPath = new DOMXPath($dom);
echo 'Built DOM and XPath', PHP_EOL;

// get documentation
echo 'Get Documentation', PHP_EOL;
$nodes = $dom->getElementsByTagName('documentation');
$doc = [
    'service' => '',
    'operations' => [],
];
foreach ($nodes as $node) {
    if ($node->parentNode->localName == 'service') {
        $doc['service'] = trim($node->parentNode->nodeValue);
        echo ' - Got a service of ', $doc['service'], PHP_EOL;
    } elseif ($node->parentNode->localName == 'operation') {
        $operation = $node->parentNode->getAttribute('name');
        //$parameterOrder = $node->parentNode->getAttribute('parameterOrder');
        $doc['operations'][$operation] = trim($node->nodeValue);
        echo ' - Got an operation of ', $doc['operations'][$operation], PHP_EOL;
    }
}

// declare service
echo 'Define Service', PHP_EOL;
$service = [
    'class' => $dom->getElementsByTagNameNS('*', 'service')->item(0)->getAttribute('name'),
    'endpoint' => $xPath->query(
        '//*[local-name()="definitions"]/*[local-name()="service"]/*[local-name()="port"]/*[local-name()="address"]/@location',
        null,
        true
    )->item(0)->nodeValue,
    'wsdl' => $wsdl,
    'doc' => $doc['service'],
    'functions' => [],
    'namespace' => null,
    'headers_in' => [],
];
echo ' - ', $service['class'], PHP_EOL;

// get targetNamespace
echo 'Get Namespaces', PHP_EOL;
$nodes = $dom->getElementsByTagName('definitions');
foreach ($nodes as $node) {
    $service['namespace'] = $node->getAttribute('targetNamespace');
    echo ' - ', $service['namespace'], PHP_EOL;
}

// get endpoint
echo 'Get Endpoint', PHP_EOL;
echo ' - ', $service['endpoint'], PHP_EOL;

// PHP keywords - can not be used as constants, class names or function names!
$reserved_keywords = [
    'and',
    'or',
    'xor',
    'as',
    'break',
    'case',
    'cfunction',
    'class',
    'continue',
    'declare',
    'const',
    'default',
    'do',
    'else',
    'elseif',
    'enddeclare',
    'endfor',
    'endforeach',
    'endif',
    'endswitch',
    'endwhile',
    'eval',
    'extends',
    'for',
    'foreach',
    'function',
    'global',
    'if',
    'new',
    'old_function',
    'static',
    'switch',
    'use',
    'var',
    'while',
    'array',
    'die',
    'echo',
    'empty',
    'exit',
    'include',
    'include_once',
    'isset',
    'list',
    'print',
    'require',
    'require_once',
    'return',
    'unset',
    '__file__',
    '__line__',
    '__function__',
    '__class__',
    'abstract',
    'private',
    'public',
    'protected',
    'throw',
    'try',
];

// ensure legal class name (I don't think using . and whitespaces is allowed in terms of the SOAP standard, should check this out and may throw and exception instead...)
$service['class'] = preg_replace('`[ \.-]`', '_', $service['class']);

if (in_array(strtolower($service['class']), $reserved_keywords)) {
    $service['class'] .= 'Service';
}

// verify that the name of the service is named as a defined class
if (class_exists($service['class'], false)) {
    throw new Exception("Class '".$service['class']."' already exists");
}

/* if(function_exists($service['class'])) {
  throw new Exception("Class '".$service['class']."' can't be used, a function with that name already exists");
  } */

// get operations
echo 'Examine operations', PHP_EOL;
$operations = $client->__getFunctions();
foreach ($operations as $operation) {
    echo ' - ', $operation, PHP_EOL;

    $matches = [];
    if (preg_match('/^(\w[\w\d_]*) (\w[\w\d_]*)\(([\w\$\d,_ ]*)\)$/', $operation, $matches)) {
        $returns = $matches[1];
        $call = $matches[2];
        $params = $matches[3];
    } elseif (preg_match('/^(list\([\w\$\d,_ ]*\)) (\w[\w\d_]*)\(([\w\$\d,_ ]*)\)$/', $operation, $matches)) {
        $returns = $matches[1];
        $call = $matches[2];
        $params = $matches[3];
    } else { // invalid function call
        throw new Exception('Invalid function call: '.$function);
    }

    $params = $params ? explode(', ', $params) : [];

    $paramsArr = [];
    foreach ($params as $param) {
        $paramsArr[] = explode(' ', $param);
    }

    $function = [
        'name' => $call,
        'method' => $call,
        'return' => $returns,
        'doc' => isset($doc['operations'][$call]) ? $doc['operations'][$call] : '',
        'params' => $paramsArr,
        'headers_in' => [],
        'headers_out' => [],
        'faults' => [],
    ];

    $xPathMaster['Headers'] = [
        'headers_in' => [
            'Definitions/Binding/Operation[method]/Input/Header' => '//*[local-name()="definitions"]/*[local-name()="binding"]/*[local-name()="operation"][@name="'.$call.'"]/*[local-name()="input"]/*[local-name()="header"]/@part',
        ],
        'headers_out' => [
            'Definitions/Binding/Operation[method]/Output/Header' => '//*[local-name()="definitions"]/*[local-name()="binding"]/*[local-name()="operation"][@name="'.$call.'"]/*[local-name()="output"]/*[local-name()="header"]/@part',
        ],
        'faults' => [
            'Definitions/Binding/Operation[method]/fault' => '//*[local-name()="definitions"]/*[local-name()="binding"]/*[local-name()="operation"][@name="'.$call.'"]/*[local-name()="fault"]/@name',
        ],
    ];

    foreach ($xPathMaster['Headers'] as $headerType => $headerXPaths) {
        foreach ($headerXPaths as $headerXPathType => $xPathRule) {
            $domPath = $xPath->query($xPathRule, null, true);
            if ($domPath->length > 0) {
                foreach ($domPath as $domItem) {
                    $function[$headerType][] = $domItem->nodeValue;
                    $service[$headerType][$domItem->nodeValue][] = $call;
                }
            }
        }
    }

    // ensure legal function name
    if (in_array(strtolower($function['method']), $reserved_keywords)) {
        $function['name'] = '_'.$function['method'];
    }

    // ensure that the method we are adding has not the same name as the constructor
    if (strtolower($service['class']) == strtolower($function['method'])) {
        $function['name'] = '_'.$function['method'];
    }

    // ensure that there's no method that already exists with this name
    // this is most likely a Soap vs HttpGet vs HttpPost problem in WSDL
    // I assume for now that Soap is the one listed first and just skip the rest
    // this should be improved by actually verifying that it's a Soap operation that's in the WSDL file
    // QUICK FIX: just skip function if it already exists
    $add = true;
    foreach ($service['functions'] as $func) {
        if ($func['name'] == $function['name']) {
            $add = false;
        }
    }
    if ($add) {
        $service['functions'][] = $function;
    }
}

echo 'Process types', PHP_EOL;
$types = $client->__getTypes();
$primitive_types = [
    'string',
    'int',
    'long',
    'float',
    'boolean',
    'dateTime',
    'double',
    'short',
    'UNKNOWN',
    'base64Binary',
    'decimal',
    'ArrayOfInt',
    'ArrayOfFloat',
    'ArrayOfString',
    'hexBinary',
]; // TODO: dateTime is special, maybe use PEAR::Date or similar
$typeAliases = [
    'boolean' => ['BaseType'=>'bool'],
    'decimal' => ['BaseType'=>'float'],
    'double' => ['BaseType'=>'float'],
];
$classNameSubstitutions = [
    'Map' => [],
    'Subs' => [],
];
$service['types'] = [];
//print_r($types);exit;
foreach ($types as $type) {
    $parts = explode("\n", $type);
    list($baseType, $class) = explode(' ', $parts[0]);

    if (substr($class, -2, 2) == '[]') { // array skipping
        continue;
    }

    if (classExists($class, $service['types'])) { // can't redeclare classes
        continue;
    }

    echo " - {$baseType} => {$class}", PHP_EOL;

    /*
     * Due to the Mac being a case insensitive file system, we have this real pain to go through where the class =>
     * filename mapping may produce a clash.
     *
     * This is also compounded by PHP itself in that the class names are case insensitive also.
     *
     * So, in the event of duplicate names, we need to generate new names for local versions and make sure they map
     * appropriately.
     */
    $classNameSubstitutions['Subs'][strtolower($class)][] = [
        'Original' => $class,
        'Renamed' => false,
    ];

    $xPathMaster['Inheritence'] = [
        'Definition/Types/Schema/ComplexType[class]/complexContent/extension' => '//*[local-name()="definitions"]/*[local-name()="types"]/*[local-name()="schema"]/*[local-name()="complexType"][@name="'.$class.'"]/*[local-name()="complexContent"]/*[local-name()="extension"]',
    ];
    $inherits = '';
    foreach ($xPathMaster['Inheritence'] as $inheritenceType => $xPathRule) {
        $domPath = $xPath->query($xPathRule, null, true);
        if ($domPath->length > 0) {
            $domBase = $xPath->query('@base', $domPath->item(0));
            if ($domBase->length > 0) {
                $inherits = stripNS($domBase->item(0)->nodeValue);
                break;
            }
        }
    }

    if (count($classNameSubstitutions['Subs'][strtolower($class)]) > 1) {
        foreach ($classNameSubstitutions['Subs'][strtolower($class)] as $subIndex => &$substitution) {
            if (false === $substitution['Renamed']) {
                $substitution['Renamed'] = $substitution['Original'].'Case'.(1 + $subIndex);
                $classNameSubstitutions['Map'][$substitution['Original']] = $substitution['Renamed'];
                echo ' *** Renamed ', $substitution['Original'], ' to ', $substitution['Renamed'], PHP_EOL;
                if (isset($service['types'][$substitution['Original']])) {
                    $service['types'][$substitution['Original']]['map'] .= 'Case'.(1 + $subIndex);
                    $service['types'][$substitution['Original']]['case'] = true;
                }
                $classCase = 'Case'.(1 + $subIndex);
                $cased = true;
            }
        }
    } else {
        $classCase = '';
        $cased = false;
    }

    $members = [];
    $ignorable = true;
    switch ($baseType) {
        case '<anyXML>':
        case 'struct':
            $ignorable = false;
            for ($i = 1; $i < count($parts) - 1; ++$i) {
                $parts[$i] = trim($parts[$i]);
                list($type, $member) = explode(' ', substr($parts[$i], 0, strlen($parts[$i]) - 1));

                $array = false;
                $mandatory = false;
                $nillable = false;
                $xPathMaster['MinMaxNillable'] = [
                    'Definition/Types/Schema/Element[class]/ComplexType/Sequeunce/Element[member]' => '//*[local-name()="definitions"]/*[local-name()="types"]/*[local-name()="schema"]/*[local-name()="element"][@name="'.$class.'"]/*[local-name()="complexType"]/*[local-name()="sequence"]/*[local-name()="element"][@name="'.$member.'"]',
                    'Definition/Types/Schema/ComplexType[class]/Sequeunce/Element[member]' => '//*[local-name()="definitions"]/*[local-name()="types"]/*[local-name()="schema"]/*[local-name()="complexType"][@name="'.$class.'"]/*[local-name()="sequence"]/*[local-name()="element"][@name="'.$member.'"]',
                    'Definition/Types/Schema/ComplexType[class]/Sequeunce/any' => '//*[local-name()="definitions"]/*[local-name()="types"]/*[local-name()="schema"]/*[local-name()="complexType"][@name="'.$class.'"]/*[local-name()="sequence"]/*[local-name()="any"]',
                ];
                foreach ($xPathMaster['MinMaxNillable'] as $inheritenceType => $xPathRule) {
                    $domPath = $xPath->query($xPathRule, null, true);
                    if ($domPath->length > 0) {
                        $minOccursDom = $xPath->query('@minOccurs', $domPath->item(0));
                        $maxOccursDom = $xPath->query('@maxOccurs', $domPath->item(0));
                        $nillableDom = $xPath->query('@nillable', $domPath->item(0));

                        if ($minOccursDom->length > 0) {
                            $min = $minOccursDom->item(0)->nodeValue;
                        } else {
                            $min = 0;
                        }

                        if ($maxOccursDom->length > 0) {
                            $max = (string) $maxOccursDom->item(0)->nodeValue;
                        } else {
                            $max = '0';
                        }

                        $nillable = ($nillableDom->length > 0 && ('true' == strtolower(
                                    $nillableDom->item(0)->nodeValue
                                )));

                        $mandatory = ($min > 0);

                        switch ($max) {
                            case 'unbounded':
                                $array = '[]';
                                $arrayRange = "$min or more";
                                break;
                            case '0':
                            case '1':
                                $array = false;
                                $arrayRange = false;
                                break;
                            default:
                                $array = '[]';
                                $arrayRange = "Between $min and $max";
                                break;
                        }
                        break;
                    }
                }

                // anyType, <anyXML> => mixed
                if (in_array($type, ['anyType', '<anyXML>'])) {
                    $type = 'mixed';
                }

                // check syntax
                if (preg_match('/^$\w[\w\d_]*$/', $member)) {
                    throw new Exception('illegal syntax for member variable: '.$member);
                    continue;
                }

                // IMPORTANT: Need to filter out namespace on member if presented
                $member = stripNS($member);

                if (!isset($member[$member])) {
                    $members[$member] = [
                        'member' => $member,
                        'type' => $type,
                        'mandatory' => $mandatory,
                        'array' => $array,
                        'arrayRange' => $arrayRange,
                        'nillable' => $nillable,
                    ];
                }
            }
            break;
        default:
            $typeAliases[$class] = ['BaseType' => $baseType];
            $domPath = $xPath->query(
                '//*[local-name()="definitions"]/*[local-name()="types"]/*[local-name()="schema"]/*[local-name()="simpleType"][@name="'.$class.'"]/*[local-name()="restriction"]'
            );
            if ($domPath->length > 0) {
                $o_Length = $xPath->query('*[local-name()="length"]/@value', $domPath->item(0));
                $o_Pattern = $xPath->query('*[local-name()="pattern"]/@value', $domPath->item(0));
                if ($o_Length->length > 0) {
                    $typeAliases[$class]['Length'] = $o_Length->item(0)->nodeValue;
                }
                if ($o_Pattern->length > 0) {
                    $typeAliases[$class]['Pattern'] = $o_Pattern->item(0)->nodeValue;
                }
            }
    }

// gather enumeration values
    $values = [];
    if (count($members) == 0) {
        $values = checkForEnum($dom, $class);
    }

    $service['types'][$class] = [
        'class' => $class,
        'members' => $members,
        'values' => $values,
        'map' => $service['class'].'_'.preg_replace('`[ \.-]`', '_', $class).$classCase,
        'ignorable' => $ignorable && empty($values),
        'case' => $cased,
        'inherits' => $inherits,
    ];
}

// Additional types for docblocks only.
$service['docblock'] = [
    'ArrayOfZReadPayment'=>'SpaService_ArrayOfZReadPayment|SpaService_ZReadPayment[]',
    'ArrayOfZReadSale'=>'SpaService_ArrayOfZReadSale|SpaService_ZReadSale[]',
];

echo 'Processing complete', PHP_EOL, 'Generating code', PHP_EOL;
$code = '';
// add types
foreach ($service['types'] as $s_Class => $type) {
    if (!$type['ignorable']) {
        echo " - class {$type['map']}";
        $inherits = (bool) $type['inherits'] ? " extends {$service['types'][$type['inherits']]['map']}" : '';
        if ($type['case']) {
            $code = '/**'.PHP_EOL.
                ' * This class was originally named '.$s_Class.PHP_EOL.
                ' * But this conflicts with another class of the same name but different case in this service.'.PHP_EOL.
                ' * This class, as well as all the other conflicting classes, have been uniquely renamed.'.PHP_EOL.
                ' */'.PHP_EOL;
        } else {
            $code = '';
        }
        $code .= "class {$type['map']}{$inherits}".PHP_EOL.
            '{'.PHP_EOL;

        if (count($type['values']) > 0) {
            $a_Enums = [];
            foreach ($type['values'] as $s_Const => $value) {
                $a_Enums[strtoupper(preg_replace('`([a-z])([A-Z])`', '$1_$2', $s_Const))] = [
                    'Value' => $value,
                    'Const' => $s_Const,
                ];
            }
            $i_Length = max(array_map('strlen', array_keys($a_Enums)));
            foreach ($a_Enums as $s_Const => $a_Enum) {
                $code .= '    /**'.PHP_EOL;
                $code .= '     * @value '.$a_Enum['Value'].PHP_EOL;
                $code .= '     */'.PHP_EOL;
                $code .= '    const '.generatePHPSymbol($s_Const)." = '{$a_Enum['Const']}';".PHP_EOL.PHP_EOL;
            }
        }

        if (count($type['members']) > 0) {
            // add member variables
            foreach ($type['members'] as $member) {
                $code .= '    /**'.PHP_EOL;
                $s_ArrayMarker = false !== $member['array'] ? $member['array'] : '';
                $s_NillableMarker = false !== $member['nillable'] ? '|null' : '';
                if (isset($typeAliases[$member['type']])) {
                    if (isset($service['types'][$member['type']]) && count($service['types'][$member['type']]['values']) > 0) {
                        $code .= "     * @var {$typeAliases[$member['type']]['BaseType']}{$s_ArrayMarker}{$s_NillableMarker} \${$member['member']} One of the constants defined in {$service['types'][$member['type']]['map']}".PHP_EOL;
                    } else {
                        $code .= "     * @basetype {$member['type']}".PHP_EOL;
                        if (isset($typeAliases[$member['type']]['Length'])) {
                            $code .= "     * @length   {$typeAliases[$member['type']]['Length']}".PHP_EOL;
                        }
                        if (isset($typeAliases[$member['type']]['Pattern'])) {
                            $code .= "     * @pattern  {$typeAliases[$member['type']]['Pattern']}".PHP_EOL;
                        }
                        $code .= "     *".PHP_EOL;
                        $code .= "     * @var      {$typeAliases[$member['type']]['BaseType']}{$s_ArrayMarker}{$s_NillableMarker} \${$member['member']}".PHP_EOL;
                    }
                } elseif (isset($service['docblock'][$member['type']])) {
                    $code .= "     * @var {$service['docblock'][$member['type']]}{$s_ArrayMarker}{$s_NillableMarker} \${$member['member']}".PHP_EOL;
                } elseif (isset($service['types'][$member['type']]['map'])) {
                    $code .= "     * @var {$service['types'][$member['type']]['map']}{$s_ArrayMarker}{$s_NillableMarker} \${$member['member']}".PHP_EOL;
                } else {
                    $code .= "     * @var {$member['type']}{$s_ArrayMarker}{$s_NillableMarker} \${$member['member']}".PHP_EOL;
                }
                if ($member['mandatory']) {
                    $code .= '     * @mandatory'.PHP_EOL;
                }
                if ($member['arrayRange']){
                    $code .= "     * @range {$member['arrayRange']}".PHP_EOL;
                }
                $code .=
                    '     */'.PHP_EOL.
                    "    public \${$member['member']};".PHP_EOL.PHP_EOL;
            }
        }
        $code = rtrim($code).PHP_EOL.'}'.PHP_EOL;

        $s_ClassFileName = 'Services/'.str_replace('_', '/', $type['map']).'.php';

        if ((bool) ($s_CodeHeader = classHeader($s_Processed, $wsdl, md5($code), $s_ClassFileName))) {
            @mkdir(dirname($s_ClassFileName), 0755, true);
            file_put_contents($s_ClassFileName, '<?php'.PHP_EOL.PHP_EOL.$s_CodeHeader.$code);
            echo PHP_EOL;
        } else {
            echo ' - Unchanged', PHP_EOL;
        }
    } else {
        echo " - ignore {$s_Class}", PHP_EOL;
        unset($service['types'][$s_Class]);
    }
}

echo 'Building service', PHP_EOL, " - {$service['class']}", PHP_EOL;
/**
 * Reset code for main class.
 */
$code = '';

// class level docblock
$code .= "\n/**\n";
$code .= " * {$service['class']} class\n";
$code .= " *\n";
if ((bool) $service['doc']) {
    $code .= parse_doc(' * ', $service['doc']);
    $code .= " *\n";
}
$code .= " * @author wsdl2php\n#READ_ONLY_PROPERTIES#";
$code .= " */\n";
$code .= "class {$service['class']} extends SoapClient\n{\n";

///**
// * Endpoint for service calls.
// * NOTE: This is not used by this class and is for your in
// */
//const SERVICE_ENDPOINT = '{$service['endpoint']}';
// Add namespace.
$code .= <<< END_NAMESPACE
    /**
     * Namespace for service calls.
     */
    const SERVICE_NAMESPACE = '{$service['namespace']}';


END_NAMESPACE;

/**
 * Add classmap.
 */
$i_Length = 2 + max(
        array_map(
            function ($type) use ($classNameSubstitutions) {
                return strlen(
                    isset($classNameSubstitutions['Map'][$type['class']]) ? $classNameSubstitutions['Map'][$type['class']] : $type['class']
                );
            },
            $service['types']
        )
    );
ksort($service['types']);
$s_ClassMapArray = implode(
    PHP_EOL,
    array_map(
        function ($a_Type) use ($i_Length) {
            return '        '."'{$a_Type['class']}'"." => '".$a_Type['map']."',";
        },
        $service['types']
    )
);
$code .= <<< END_CLASSMAP
    /**
     * Default class mapping for this service.
     *
     * @var array
     */
    private static \$classMap = [
{$s_ClassMapArray}
    ];


END_CLASSMAP;

/**
 * Build a store of protected properties for __get() and __isset().
 */
$listOfProtectedProperties = [];
$protectedPropertiesCases = '';
$readOnlyProperties = '';

/*
 * Add SOAP Headers.
 *
 * Handle the situation where the header is in the request and the response. We only want 1 entry in the class.
 */
if (!empty($service['headers_in'])) {
    ksort($service['headers_in']);
    foreach ($service['headers_in'] as $s_SOAPHeader => $a_Usages) {
        if ($b_InAndOut = isset($service['headers_out'][$s_SOAPHeader])) {
            $a_Usages = array_unique(array_merge($a_Usages, $service['headers_out'][$s_SOAPHeader]));
            sort($a_Usages);
        }
        $s_Usages = implode(
            PHP_EOL,
            array_map(
                function ($s_Usage) use ($service, $s_SOAPHeader, $b_InAndOut) {
                    return "     * @usedby {$service['types'][$s_Usage]['map']}".($b_InAndOut && in_array(
                        $s_Usage,
                        $service['headers_out'][$s_SOAPHeader]
                    ) ? ' for request and response' : ' for request only');
                },
                $a_Usages
            )
        );

        $s_InAndOut = $b_InAndOut ? ' and received with response' : '';

        $code .= <<< END_SOAP_HEADER
    /**
     * SOAP Header sent with request{$s_InAndOut}
     *
     * @var {$service['types'][$s_SOAPHeader]['map']} SOAP Header sent with request{$s_InAndOut}
{$s_Usages}
     */
    protected \$o_{$s_SOAPHeader};


END_SOAP_HEADER;
        $listOfProtectedProperties[$s_SOAPHeader] = [
            'Property' => "o_{$s_SOAPHeader}",
            'Label' => 'SOAP Header sent with request',
        ];
        if ($b_InAndOut) {
            unset($service['headers_out'][$s_SOAPHeader]);
        }
    }
}
if (!empty($service['headers_out'])) {
    ksort($service['headers_out']);
    foreach ($service['headers_out'] as $s_SOAPHeader => $a_Usages) {
        $s_Usages = implode(
            PHP_EOL,
            array_map(
                function ($s_Usage) use ($service) {
                    return "     * @usedby {$service['types'][$s_Usage]['map']} for response only";
                },
                $a_Usages
            )
        );
        $code .= <<< END_SOAP_HEADER
    /**
     * SOAP Header received with response
     *
     * @param {$service['types'][$s_SOAPHeader]['map']} \$o_{$s_SOAPHeader} SOAP Header received with response
{$s_Usages}
     */
    protected \$o_{$s_SOAPHeader};


END_SOAP_HEADER;
        $listOfProtectedProperties[$s_SOAPHeader] = [
            'Property' => "o_{$s_SOAPHeader}",
            'Label' => 'SOAP Header received with response',
        ];
    }
}

// Add constructor
$code .= <<< 'END_PHP'
    /**
     * Service Constructor
     *
     * @param string $wsdl The location of the WSDL file.
     * @param array $options Any additional parameters to add to the service.
     */
    public function __construct(string $wsdl = null, array $options = [])
    {
        // Use the optional WSDL file location if it is supplied.
        $wsdl = is_null($wsdl) ? #LOCALISED_WSDL# : $wsdl;

        // Add the classmap to the options.
        foreach (self::$classMap as $serviceClassName => $mappedClassName) {
            if (!isset($options['classmap'][$serviceClassName])) {
                $options['classmap'][$serviceClassName] = $mappedClassName;
            }
        }

        parent::__construct($wsdl, $options);
    }


END_PHP;

// Build __get() and __isset() if there are any protected properties and build the strings for the header docblock.
if (!empty($listOfProtectedProperties)) {
    ksort($listOfProtectedProperties);
    $protectedPropertiesCases = implode(
        PHP_EOL,
        array_map(
            function (array $protectedProperty) {
                return "            case '{$protectedProperty['Property']}' :";
            },
            $listOfProtectedProperties
        )
    );
    $maxLenType = max(
        array_map(
            function (array $protectedProperty) use ($service) {
                return strlen($service['types'][substr($protectedProperty['Property'], 2)]['map']);
            },
            $listOfProtectedProperties
        )
    );
    $maxLenProperty = max(
        array_map(
            function (array $protectedProperty) {
                return strlen($protectedProperty['Property']);
            },
            $listOfProtectedProperties
        )
    );
    $readOnlyProperties = implode(
            PHP_EOL,
            array_map(
                function (array $protectedProperty) use ($service, $maxLenType, $maxLenProperty) {
                    $protectedPropertyName = substr($protectedProperty['Property'], 2);

                    return ' * @property-read '.str_pad(
                        $service['types'][$protectedPropertyName]['map'],
                        $maxLenType + 1
                    ).'$'.str_pad(
                        $protectedProperty['Property'],
                        $maxLenProperty
                    )." {$protectedProperty['Label']}";
                },
                $listOfProtectedProperties
            )
        ).PHP_EOL;

    $code .= <<< 'END_PHP'
    /**
     * Getter for protected properties.
     *
     * @param string $property
     *
     * @return mixed
     */
    public function __get(string $property)
    {
        switch ($property) {
#PROTECTED_PROPERTIES#
                $result = $this->$property;
                break;
            default :
                $result = parent::__get($property);
        }

        return $result;
    }

    /**
     * Isseter for protected properties.
     *
     * @param string $property
     *
     * @return bool
     */
    public function __isset(string $property): bool
    {
        switch ($property) {
#PROTECTED_PROPERTIES#
                $result = isset($this->$property);
                break;
            default :
                $result = parent::__isset($property);
        }

        return $result;
    }


END_PHP;
}

//Add call proxy and soap header assignment logic.
$code .= <<< 'END_PHP'
    /**
     * Service call proxy.
     *
     * @param string $serviceName The name of the service being called.
     * @param array $parameters The parameters being supplied to the service.
     * @param SOAPHeader[] $requestHeaders An array of SOAPHeaders.
     *
     * @return mixed The service response.
     */
    protected function callProxy(string $serviceName, array $parameters = null, array $requestHeaders = null)
    {
        $result = $this->__soapCall(
            $serviceName,
            $parameters,
            [
                'uri' => 'http://tempuri.org/',
                'soapaction' => '',
            ],
            !empty($requestHeaders) ? array_filter($requestHeaders) : null,
            $responseHeaders
        );

        if (!empty($responseHeaders)) {
            foreach ($responseHeaders as $headerName => $headerData) {
                $this->$headerName = $headerData;
            }
        }

        return $result;
    }

    /**
     * Build and populate a SOAP header.
     *
     * @param string $headerName The name of the services SOAP Header.
     * @param array|object $rawHeaderData Any data that can be mapped to the SOAP Header. Public properties of objects will be used if an object is supplied.
     * @param string $namespace The namespace which will default to this service's namespace.
     *
     * @throws ReflectionException
     */
    public function assignSoapHeader(string $headerName, $rawHeaderData = null, string $namespace = self::SERVICE_NAMESPACE)
    {
        // Is there a corresponding property of this service for the requested SOAP Header?
        // Is there a mapped class for this SOAP Header?
        // Do we have any data to populate the SOAP Header with?
        if (property_exists($this, $headerName) && isset(self::$classMap[$headerName]) && !empty($rawHeaderData)) {
            // Start with no data for the SOAP Header.
            $dataForSoapHeader = [];
            $mappedData = [];

            // Get the mapped class and get the properties defined for the SOAP Header.
            $reflectedHeader = new ReflectionClass(self::$classMap[$headerName]);
            $reflectedHeaderProperties = $reflectedHeader->getProperties();

            // Produce an array of public data from an object.
            if (is_object($rawHeaderData)) {
                $reflectedData = new ReflectionClass($rawHeaderData);
                $reflectedDataProperties = $reflectedData->getProperties(ReflectionProperty::IS_PUBLIC);
                $mappedData = [];
                foreach ($reflectedDataProperties as $property) {
                    $propertyName = $property->name;
                    $mappedData[$propertyName] = $rawHeaderData->$propertyName;
                }
            } elseif (is_array($rawHeaderData)) {
                $mappedData = $rawHeaderData;
            }

            // Process the data as an array.
            if (!empty($mappedData)) {
                foreach ($reflectedHeaderProperties as $property) {
                    $propertyName = $property->name;
                    if (isset($mappedData[$propertyName])) {
                        $dataForSoapHeader[$propertyName] = $mappedData[$propertyName];
                    }
                }
            }

            // Build the SOAP Header and assign it the corresponding property.
            $this->$headerName = new SoapHeader($namespace, $headerName, $dataForSoapHeader);
        }
    }


END_PHP;
$code = str_replace(
    '#LOCALISED_WSDL#',
    is_file($wsdl) ? "__DIR__ . '/{$service['class']}/{$service['class']}.wsdl'" : "'{$wsdl}'",
    $code
);
$code = str_replace('#READ_ONLY_PROPERTIES#', $readOnlyProperties, $code);
if (!empty($protectedPropertiesCases)) {
    $code = str_replace('#PROTECTED_PROPERTIES#', $protectedPropertiesCases, $code);
}

echo 'Adding Methods', PHP_EOL;
foreach ($service['functions'] as $function) {
    echo " - {$function['name']}", PHP_EOL;
    $code .= '    /**'.PHP_EOL;
    if ((bool) $function['doc']) {
        $code .= parse_doc('     * ', $function['doc']);
        $code .= '     *'.PHP_EOL;
    }

    $signature = []; // used for function signature
    $para = []; // just variable names
    if (count($function['params']) > 0) {
        foreach ($function['params'] as $param) {
            $code .= '     * @param '.(isset($param[0]) && isset($service['types'][$param[0]]['map']) ? $service['types'][$param[0]]['map'] : $param[0]).' '.(isset($param[1]) ? $param[1] : '')."\n";
            $signature[] = (in_array($param[0], $primitive_types) or substr(
                    $param[0],
                    0,
                    7
                ) == 'ArrayOf') ? $param[1] : (isset($param[0]) ? $service['types'][$param[0]]['map'] : '').' '.(isset($param[1]) ? $param[1] : '');
            $para[] = $param[1];
        }
    }
    $code .= '     *'.PHP_EOL;
    $code .= "     * @return {$service['types'][$function['return']]['map']}".PHP_EOL;

    if (!empty($function['headers_in'])) {
        $code .= '     *'.PHP_EOL;
        $code .= '     * This service call may use the following SOAPHeaders:'.PHP_EOL;
        foreach ($function['headers_in'] as $s_Header) {
            $code .= "     * @SOAPHeaderRequest {$service['types'][$s_Header]['map']}".PHP_EOL;
        }
    }

    if (!empty($function['headers_out'])) {
        $code .= '     *'.PHP_EOL;
        $code .= "     * This service call's response may contain the following SOAPHeaders:".PHP_EOL;
        foreach ($function['headers_out'] as $i_Header => $s_Header) {
            $code .= "     * @SOAPHeaderResponse {$service['types'][$s_Header]['map']}".PHP_EOL;
        }
    }

    if (!empty($function['faults'])) {
        $code .= '     *'.PHP_EOL;
        $code .= '     * This service call may generate the following Service Level Faults:'.PHP_EOL;
        foreach ($function['faults'] as $i_Fault => $s_Fault) {
            $code .= "     * @SOAPFault {$service['types'][$s_Fault]['map']}".PHP_EOL;
        }
    }

    $code .= "     */\n";
    $code .= "    public function {$function['name']}(".implode(', ', $signature).")\n    {\n";
    $code .= "        return \$this->callProxy('{$function['method']}', [";
    $params = [];
    if (count($signature) > 0) { // add arguments
        foreach ($signature as $param) {
            if (strpos($param, ' ')) { // slice
                $tmp_param = explode(' ', $param);
                $param = array_pop($tmp_param);
            }
            $params[] = $param;
        }
        $code .= implode(', ', $params);
    }
    $code .= ']';
    if (!empty($function['headers_in'])) {
        if (!empty($function['headers_in'])) {
            $code .= ', array('.PHP_EOL;
            foreach ($function['headers_in'] as $s_Header) {
                $code .= '                $this->'.$s_Header.','.PHP_EOL;
            }
            $code .= '        )';
        }
    }

    $code .= ");\n    }\n\n";
}
$code = rtrim($code)."\n}\n";

echo "Writing {$service['class']}.php";
$s_ClassFileName = 'Services/'.str_replace('_', '/', $service['class']).'.php';
if ((bool) ($s_CodeHeader = classHeader($s_Processed, $wsdl, md5($code), $s_ClassFileName))) {
    @mkdir(dirname($s_ClassFileName), 0755, true);
    file_put_contents($s_ClassFileName, '<?php'.PHP_EOL.PHP_EOL.$s_CodeHeader.$code);
    echo PHP_EOL;
} else {
    echo ' - Unchanged', PHP_EOL;
}

echo 'Saving WSDL file', PHP_EOL;
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$s_SavedWSDL = 'Services/'.str_replace('_', '/', $service['class'])."/{$service['class']}.wsdl";
$dom->save($s_SavedWSDL);
echo " - {$s_SavedWSDL}", PHP_EOL, 'Finished', PHP_EOL;

//print_r($service);

function classHeader($s_Processed, $s_WSDLURL, $s_MD5, $s_ClassFileName)
{
    $s_WSDL2PHP = date('r', filemtime(__FILE__));
    $s_WSDLURL = is_file($s_WSDLURL) ? substr($s_WSDLURL, 1 + strlen(__DIR__)) : $s_WSDLURL;
    $s_Result = <<< END_DOCBLOCK
/**
 * This class was created using wsdl2php.
 *
 * @wsdl2php  {$s_WSDL2PHP} - Last modified
 * @WSDL      {$s_WSDLURL}
 * @Processed {$s_Processed}
 * @Hash      {$s_MD5}
 */

END_DOCBLOCK;

    if (file_exists($s_ClassFileName)) {
        preg_match('`^.*? \*/\s++(.++)`s', file_get_contents($s_ClassFileName), $a_Match);
        $s_Result = $s_MD5 == md5($a_Match[1]) ? false : $s_Result;
    }

    return $s_Result;
}

function parse_doc($prefix, $doc)
{
    $width = 100;

    // Tag OL.
    $doc = preg_replace('`^(\d++\.)`sim', '<OL>\1', $doc);

    // Tag UL.
    $doc = preg_replace('`^- `sim', '<UL>', $doc);

    // Join broken lines (but not OL or UL tags).
    $doc = preg_replace('`([^\n])\n(?!\n|<OL>|<UL>)`sim', '\1 \2', $doc);

    // Break string into lines.
    $lines = preg_split('`\r\n|\r|\n`sim', $doc);

    // Word wrap each line to 96 characters, 92 for <OL>
    $lines = array_map(
        function ($line) use ($width, $prefix) {
            if (in_array(substr($line, 0, 4), ['<OL>', '<UL>'])) {
                $line = substr_replace($line, '    ', 0, 4);
                $width -= strlen($prefix) + 4;
                $prefix = '       ';
            } else {
                $width -= strlen($prefix);
                $prefix = '';
            }

            return wordwrap($line, $width, "\n$prefix", false);
        },
        $lines
    );

    // Join and break again to account for <OL>
    $lines = preg_split('`\r\n|\r|\n`sim', implode("\n", $lines));

    // Add prefix
    $lines = array_map(
        function ($line) use ($prefix) {
            return $prefix.$line;
        },
        $lines
    );

    // Join
    $doc = implode("\n", $lines);

    return $doc."\n";
}

/**
 * Look for enumeration.
 *
 * @param DOM    $dom
 * @param string $class
 *
 * @return array
 */
function checkForEnum(&$dom, $class)
{
    $values = [];

    $node = findType($dom, $class);
    if (!$node) {
        return $values;
    }

    $enum_list = $node->getElementsByTagName('enumeration');
    if ($enum_list->length == 0) {
        return $values;
    }

    for ($i = 0; $i < $enum_list->length; ++$i) {
        $value = $enum_list->item(
            $i
        )->attributes->getNamedItem('value')->nodeValue;
        $value_node = $enum_list->item(
            $i
        )->getElementsByTagName('annotation');
        $values[$enum_list->item($i)->attributes->getNamedItem(
            'value'
        )->nodeValue] = $value_node->length == 1 ? $enum_list->item(
            $i
        )->getElementsByTagName('annotation')->item(0)->nodeValue : $value;
    }

    return $values;
}

/**
 * Look for a type.
 *
 * @param DOM    $dom
 * @param string $class
 *
 * @return DOMNode
 */
function findType(&$dom, $class)
{
    $types_node = $dom->getElementsByTagName('types')->item(0);
    $schema_list = $types_node->getElementsByTagName('schema');

    for ($i = 0; $i < $schema_list->length; ++$i) {
        $children = $schema_list->item($i)->childNodes;
        for ($j = 0; $j < $children->length; ++$j) {
            $node = $children->item($j);
            if ($node instanceof DOMElement &&
                $node->hasAttributes() &&
                $node->attributes->getNamedItem('name') &&
                $node->attributes->getNamedItem('name')->nodeValue == $class
            ) {
                return $node;
            }
        }
    }

    return null;
}

function generatePHPSymbol($s)
{
    global $reserved_keywords;

    if (!preg_match('/^[A-Za-z_]/', $s)) {
        $s = 'value_'.$s;
    }
    if (in_array(strtolower($s), $reserved_keywords)) {
        $s = '_'.$s;
    }

    return preg_replace('/[-:.\s]/', '_', $s);
}

function classExists($classname, $service_types)
{
    foreach ($service_types as $service_type) {
        if ($service_type['class'] == $classname) {
            return true;
        }
    }

    return false;
}

function stripNS($s_Element)
{
    if (strpos($s_Element, ':')) { // keep the last part
        list(, $s_Element) = explode(':', $s_Element);
    }

    return $s_Element;
}
