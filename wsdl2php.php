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

$s_WSDL = $_SERVER['argv'][1];

echo "Analyzing WSDL of ", $s_WSDL, PHP_EOL;
$s_Processed = date('r');

try {
    $client = new SoapClient(
        $s_WSDL, [
            'encoding'   => 'UTF-8',
            'exception'  => true,
            'trace'      => true,
            'user_agent' => 'PHP',
        ]
    );
}
catch (SoapFault $e) {
    echo
        // Show exception.
    'Exception', PHP_EOL,
    '---------', PHP_EOL,
    $e->getMessage(), PHP_EOL;
}
catch (Exception $e) {
    // As we are going to need to display XML, use Tidy to make it pretty.
    $a_TidyConfig = [
        'indent'            => true,
        'indent-spaces'     => 4,
        'indent-attributes' => true,
        'input-xml'         => true,
        'output-xml'        => true,
        'wrap'              => 0,
    ];
    $o_Tidy       = new Tidy;

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
$dom->load($s_WSDL);
$o_XPath = new DOMXPath($dom);
echo 'Built DOM and XPath', PHP_EOL;

// get documentation
echo 'Get Documentation', PHP_EOL;
$nodes = $dom->getElementsByTagName('documentation');
$doc   = [
    'service'    => '',
    'operations' => []
];
foreach ($nodes as $node) {
    if ($node->parentNode->localName == 'service') {
        $doc['service'] = trim($node->parentNode->nodeValue);
        echo ' - Got a service of ', $doc['service'], PHP_EOL;
    } else if ($node->parentNode->localName == 'operation') {
        $operation = $node->parentNode->getAttribute('name');
        //$parameterOrder = $node->parentNode->getAttribute('parameterOrder');
        $doc['operations'][$operation] = trim($node->nodeValue);
        echo ' - Got an operation of ', $doc['operations'][$operation], PHP_EOL;
    }
}

// declare service
echo 'Define Service', PHP_EOL;
$service = [
    'class'      => $dom->getElementsByTagNameNS('*', 'service')->item(0)->getAttribute('name'),
    'endpoint'   => $o_XPath->query(
        '//*[local-name()="definitions"]/*[local-name()="service"]/*[local-name()="port"]/*[local-name()="address"]/@location',
        null,
        true
    )->item(0)->nodeValue,
    'wsdl'       => $s_WSDL,
    'doc'        => $doc['service'],
    'functions'  => [],
    'namespace'  => null,
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
    'try'
];

// ensure legal class name (I don't think using . and whitespaces is allowed in terms of the SOAP standard, should check this out and may throw and exception instead...)
$service['class'] = preg_replace('`[ \.-]`', '_', $service['class']);

if (in_array(strtolower($service['class']), $reserved_keywords)) {
    $service['class'] .= 'Service';
}

// verify that the name of the service is named as a defined class
if (class_exists($service['class'], false)) {
    throw new Exception("Class '" . $service['class'] . "' already exists");
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
        $call    = $matches[2];
        $params  = $matches[3];
    } else if (preg_match('/^(list\([\w\$\d,_ ]*\)) (\w[\w\d_]*)\(([\w\$\d,_ ]*)\)$/', $operation, $matches)) {
        $returns = $matches[1];
        $call    = $matches[2];
        $params  = $matches[3];
    } else { // invalid function call
        throw new Exception('Invalid function call: ' . $function);
    }

    $params = $params ? explode(', ', $params) : [];

    $paramsArr = [];
    foreach ($params as $param) {
        $paramsArr[] = explode(' ', $param);
    }

    $function = [
        'name'        => $call,
        'method'      => $call,
        'return'      => $returns,
        'doc'         => isset($doc['operations'][$call]) ? $doc['operations'][$call] : '',
        'params'      => $paramsArr,
        'headers_in'  => [],
        'headers_out' => [],
        'faults'      => [],
    ];

    $a_XPaths['Headers'] = [
        'headers_in'  => [
            'Definitions/Binding/Operation[method]/Input/Header' => '//*[local-name()="definitions"]/*[local-name()="binding"]/*[local-name()="operation"][@name="' . $call . '"]/*[local-name()="input"]/*[local-name()="header"]/@part',
        ],
        'headers_out' => [
            'Definitions/Binding/Operation[method]/Output/Header' => '//*[local-name()="definitions"]/*[local-name()="binding"]/*[local-name()="operation"][@name="' . $call . '"]/*[local-name()="output"]/*[local-name()="header"]/@part',
        ],
        'faults'      => [
            'Definitions/Binding/Operation[method]/fault' => '//*[local-name()="definitions"]/*[local-name()="binding"]/*[local-name()="operation"][@name="' . $call . '"]/*[local-name()="fault"]/@name',
        ],
    ];

    foreach ($a_XPaths['Headers'] as $s_Type => $a_HeaderXPaths) {
        foreach ($a_HeaderXPaths as $s_XPathType => $s_XPath) {
            $o_Path = $o_XPath->query($s_XPath, null, true);
            if ($o_Path->length > 0) {
                foreach ($o_Path as $o_PathItem) {
                    $function[$s_Type][]                        = $o_PathItem->nodeValue;
                    $service[$s_Type][$o_PathItem->nodeValue][] = $call;
                }
            }
        }
    }

    // ensure legal function name
    if (in_array(strtolower($function['method']), $reserved_keywords)) {
        $function['name'] = '_' . $function['method'];
    }

    // ensure that the method we are adding has not the same name as the constructor
    if (strtolower($service['class']) == strtolower($function['method'])) {
        $function['name'] = '_' . $function['method'];
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
$types            = $client->__getTypes();
$primitive_types  = [
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
    'decimal',
    'hexBinary'
]; // TODO: dateTime is special, maybe use PEAR::Date or similar
$a_Aliases        = [];
$a_ClassNameSubs  = [
    'Map'  => [],
    'Subs' => [],
];
$a_Inheritence    = [];
$service['types'] = [];
//print_r($types);exit;
foreach ($types as $type) {
    $parts = explode("\n", $type);
    list($s_BaseType, $class) = explode(" ", $parts[0]);

    if (substr($class, -2, 2) == '[]') { // array skipping
        continue;
    }

    if (classExists($class, $service['types'])) { // can't redeclare classes
        continue;
    }

    echo " - {$s_BaseType} => {$class}", PHP_EOL;

    /**
     * Due to the Mac being a case insensitive file system, we have this real pain to go through where the class =>
     * filename mapping may produce a clash.
     *
     * This is also compounded by PHP itself in that the class names are case insensitive also.
     *
     * So, in the event of duplicate names, we need to generate new names for local versions and make sure they map
     * appropriately.
     */
    $a_ClassNameSubs['Subs'][strtolower($class)][] = [
        'Original' => $class,
        'Renamed'  => false,
    ];

    $a_XPaths['Inheritence'] = [
        'Definition/Types/Schema/ComplexType[class]/complexContent/extension' => '//*[local-name()="definitions"]/*[local-name()="types"]/*[local-name()="schema"]/*[local-name()="complexType"][@name="' . $class . '"]/*[local-name()="complexContent"]/*[local-name()="extension"]',
    ];
    $s_Inherits              = '';
    foreach ($a_XPaths['Inheritence'] as $s_PathType => $s_XPath) {
        $o_Path = $o_XPath->query($s_XPath, null, true);
        if ($o_Path->length > 0) {
            $o_Base = $o_XPath->query('@base', $o_Path->item(0));
            if ($o_Base->length > 0) {
                $s_Inherits = stripNS($o_Base->item(0)->nodeValue);
                break;
            }
        }
    }

    if (count($a_ClassNameSubs['Subs'][strtolower($class)]) > 1) {
        foreach ($a_ClassNameSubs['Subs'][strtolower($class)] as $i_Sub => &$a_Sub) {
            if (false === $a_Sub['Renamed']) {
                $a_Sub['Renamed']                           = $a_Sub['Original'] . 'Case' . (1 + $i_Sub);
                $a_ClassNameSubs['Map'][$a_Sub['Original']] = $a_Sub['Renamed'];
                echo ' *** Renamed ', $a_Sub['Original'], ' to ', $a_Sub['Renamed'], PHP_EOL;
                if (isset($service['types'][$a_Sub['Original']])) {
                    $service['types'][$a_Sub['Original']]['map'] .= 'Case' . (1 + $i_Sub);
                    $service['types'][$a_Sub['Original']]['case'] = true;
                }
                $s_ClassCase = 'Case' . (1 + $i_Sub);
                $b_Case      = true;
            }
        }
    } else {
        $s_ClassCase = '';
        $b_Case      = false;
    }

    $members     = [];
    $b_Ignorable = true;
    switch ($s_BaseType) {
        case '<anyXML>' :
        case 'struct' :
            $b_Ignorable = false;
            for ($i = 1; $i < count($parts) - 1; $i++) {
                $parts[$i] = trim($parts[$i]);
                list($type, $member) = explode(" ", substr($parts[$i], 0, strlen($parts[$i]) - 1));

                $array                      = false;
                $mandatory                  = false;
                $nillable                   = false;
                $a_XPaths['MinMaxNillable'] = [
                    'Definition/Types/Schema/Element[class]/ComplexType/Sequeunce/Element[member]' => '//*[local-name()="definitions"]/*[local-name()="types"]/*[local-name()="schema"]/*[local-name()="element"][@name="' . $class . '"]/*[local-name()="complexType"]/*[local-name()="sequence"]/*[local-name()="element"][@name="' . $member . '"]',
                    'Definition/Types/Schema/ComplexType[class]/Sequeunce/Element[member]'         => '//*[local-name()="definitions"]/*[local-name()="types"]/*[local-name()="schema"]/*[local-name()="complexType"][@name="' . $class . '"]/*[local-name()="sequence"]/*[local-name()="element"][@name="' . $member . '"]',
                    'Definition/Types/Schema/ComplexType[class]/Sequeunce/any'                     => '//*[local-name()="definitions"]/*[local-name()="types"]/*[local-name()="schema"]/*[local-name()="complexType"][@name="' . $class . '"]/*[local-name()="sequence"]/*[local-name()="any"]',
                ];
                foreach ($a_XPaths['MinMaxNillable'] as $s_PathType => $s_XPath) {
                    $o_Path = $o_XPath->query($s_XPath, null, true);
                    if ($o_Path->length > 0) {
                        $o_Min      = $o_XPath->query('@minOccurs', $o_Path->item(0));
                        $o_Max      = $o_XPath->query('@maxOccurs', $o_Path->item(0));
                        $o_Nillable = $o_XPath->query('@nillable', $o_Path->item(0));

                        if ($o_Min->length > 0) {
                            $min = $o_Min->item(0)->nodeValue;
                        } else {
                            $min = 0;
                        }

                        if ($o_Max->length > 0) {
                            $max = (string)$o_Max->item(0)->nodeValue;
                        } else {
                            $max = '0';
                        }

                        $nillable = ($o_Nillable->length > 0 && ('true' == strtolower(
                                    $o_Nillable->item(0)->nodeValue
                                )));

                        $mandatory = ($min > 0);

                        switch ($max) {
                            case 'unbounded' :
                                $array = "[{$min}..]";
                                break;
                            case '0' :
                            case '1' :
                                $array = false;
                                break;
                            default :
                                $array = "[{$min}..{$max}]";
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
                    throw new Exception('illegal syntax for member variable: ' . $member);
                    continue;
                }

                // IMPORTANT: Need to filter out namespace on member if presented
                $member = stripNS($member);

                if (!isset($member[$member])) {
                    $members[$member] = [
                        'member'    => $member,
                        'type'      => $type,
                        'mandatory' => $mandatory,
                        'array'     => $array,
                        'nillable'  => $nillable,
                    ];
                }
            }
            break;
        default :
            $a_Aliases[$class] = ['BaseType' => $s_BaseType];
            $o_Path            = $o_XPath->query(
                '//*[local-name()="definitions"]/*[local-name()="types"]/*[local-name()="schema"]/*[local-name()="simpleType"][@name="' . $class . '"]/*[local-name()="restriction"]'
            );
            if ($o_Path->length > 0) {
                $o_Length  = $o_XPath->query('*[local-name()="length"]/@value', $o_Path->item(0));
                $o_Pattern = $o_XPath->query('*[local-name()="pattern"]/@value', $o_Path->item(0));
                if ($o_Length->length > 0) {
                    $a_Aliases[$class]['Length'] = $o_Length->item(0)->nodeValue;
                }
                if ($o_Pattern->length > 0) {
                    $a_Aliases[$class]['Pattern'] = $o_Pattern->item(0)->nodeValue;
                }
            }
    }

// gather enumeration values
    $values = [];
    if (count($members) == 0) {
        $values = checkForEnum($dom, $class);
    }

    $service['types'][$class] = [
        'class'     => $class,
        'members'   => $members,
        'values'    => $values,
        'map'       => $service['class'] . '_' . preg_replace('`[ \.-]`', '_', $class) . $s_ClassCase,
        'ignorable' => $b_Ignorable && empty($values),
        'case'      => $b_Case,
        'inherits'  => $s_Inherits,
    ];
}

echo 'Processing complete', PHP_EOL, 'Generating code', PHP_EOL;
$code = "";
// add types
foreach ($service['types'] as $s_Class => $type) {
    if (!$type['ignorable']) {
        echo " - class {$type['map']}";
        $s_Inherits = !!$type['inherits'] ? " extends {$service['types'][$type['inherits']]['map']}" : '';
        if ($type['case']) {
            $code = '/**' . PHP_EOL .
                ' * This class was originally named ' . $s_Class . PHP_EOL .
                ' * But this conflicts with another class of the same name but different case in this service.' . PHP_EOL .
                ' * This class, as well as all the other conflicting classes, have been uniquely renamed.' . PHP_EOL .
                ' */' . PHP_EOL;
        } else {
            $code = '';
        }
        $code .= "class {$type['map']}{$s_Inherits}" . PHP_EOL .
            "{" . PHP_EOL;

        if (count($type['values']) > 0) {
            $a_Enums = [];
            foreach ($type['values'] as $s_Const => $value) {
                $a_Enums[strtoupper(preg_replace('`([a-z])([A-Z])`', '$1_$2', $s_Const))] = [
                    'Value' => $value,
                    'Const' => $s_Const,
                ];
            }
            $i_Length = max(array_map('strlen', array_keys($a_Enums)));
            $code .= PHP_EOL;
            foreach ($a_Enums as $s_Const => $a_Enum) {
                $code .= '    /**' . PHP_EOL;
                $code .= '     * @value ' . $a_Enum['Value'] . PHP_EOL;
                $code .= '     */' . PHP_EOL;
                $code .= '    const ' . str_pad(
                        generatePHPSymbol($s_Const),
                        $i_Length,
                        ' '
                    ) . " = '{$a_Enum['Const']}';" . PHP_EOL . PHP_EOL;
            }
        }

        if (count($type['members']) > 0) {
            // add member variables
            foreach ($type['members'] as $member) {
                $code .= PHP_EOL .
                    '    /**' . PHP_EOL;
                $s_ArrayMarker    = false !== $member['array'] ? $member['array'] : '';
                $s_NillableMarker = false !== $member['nillable'] ? '|Null' : '';
                if (isset($a_Aliases[$member['type']])) {
                    if (isset($service['types'][$member['type']]) && count(
                            $service['types'][$member['type']]['values']
                        ) > 0
                    ) {
                        $code .= "     * @var {$a_Aliases[$member['type']]['BaseType']}{$s_ArrayMarker}{$s_NillableMarker} \${$member['member']} One of the constants defined in {$service['types'][$member['type']]['map']}" . PHP_EOL;
                    } else {
                        $code .= "     * @basetype {$member['type']}" . PHP_EOL;
                        if (isset($a_Aliases[$member['type']]['Length'])) {
                            $code .= "     * @length   {$a_Aliases[$member['type']]['Length']}" . PHP_EOL;
                        }
                        if (isset($a_Aliases[$member['type']]['Pattern'])) {
                            $code .= "     * @pattern  {$a_Aliases[$member['type']]['Pattern']}" . PHP_EOL;
                        }
                        $code .= "     * @var      {$a_Aliases[$member['type']]['BaseType']}{$s_ArrayMarker}{$s_NillableMarker} \${$member['member']}" . PHP_EOL;
                    }
                } else if (isset($service['types'][$member['type']]['map'])) {
                    $code .= "     * @var {$service['types'][$member['type']]['map']}{$s_ArrayMarker}{$s_NillableMarker} \${$member['member']}" . PHP_EOL;
                } else {
                    $code .= "     * @var {$member['type']}{$s_ArrayMarker}{$s_NillableMarker} \${$member['member']}" . PHP_EOL;
                }
                if ($member['mandatory']) {
                    $code .= "     * @mandatory" . PHP_EOL;
                }
                $code .=
                    '     */' . PHP_EOL .
                    "    public \${$member['member']};" . PHP_EOL;
            }
        }
        $code .= PHP_EOL . '}' . PHP_EOL;

        $s_ClassFileName = 'Services/' . str_replace('_', '/', $type['map']) . '.php';

        if (!!($s_CodeHeader = classHeader($s_Processed, $s_WSDL, md5($code), $s_ClassFileName))) {
            @mkdir(dirname($s_ClassFileName), 0755, true);
            file_put_contents($s_ClassFileName, "<?php" . PHP_EOL . PHP_EOL . $s_CodeHeader . $code);
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
$code .= "/**\n";
$code .= " * {$service['class']} class\n";
$code .= " *\n";
if (!!$service['doc']) {
    $code .= parse_doc(" * ", $service['doc']);
    $code .= " *\n";
}
$code .= " * @author wsdl2php\n#READ_ONLY_PROPERTIES#";
$code .= " */\n";
$code .= "class {$service['class']} extends SoapClient\n{\n\n";

/**
 * Add namespace and endpoint.
 */
$code .= <<< END_NAMESPACE_ENDPOINT
    /**
     * Endpoint for service calls.
     */
    const SERVICE_ENDPOINT = '{$service['endpoint']}';

    /**
     * Namespace for service calls.
     */
    const SERVICE_NAMESPACE = '{$service['namespace']}';


END_NAMESPACE_ENDPOINT;

/**
 * Add classmap
 */
$i_Length = 2 + max(
        array_map(
            function ($type) use ($a_ClassNameSubs) {
                return strlen(
                    isset($a_ClassNameSubs['Map'][$type['class']]) ? $a_ClassNameSubs['Map'][$type['class']] : $type['class']
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
            return '        ' . str_pad("'{$a_Type['class']}'", $i_Length, ' ') . " => '" . $a_Type['map'] . "',";
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
    private static \$a_ClassMap = array(
{$s_ClassMapArray}
    );


END_CLASSMAP;

/**
 * Build a store of protected properties for __get() and __isset().
 */
$a_ProtectedProperties = [];
$s_ProtectedProperties = '';
$s_ReadOnlyProperties  = '';

/**
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
                    return "     * @usedby {$service['types'][$s_Usage]['map']}" . ($b_InAndOut && in_array(
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
        $a_ProtectedProperties[$s_SOAPHeader] = [
            'Property' => "o_{$s_SOAPHeader}",
            'Label'    => 'SOAP Header sent with request',
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
        $a_ProtectedProperties[$s_SOAPHeader] = [
            'Property' => "o_{$s_SOAPHeader}",
            'Label'    => 'SOAP Header received with response',
        ];
    }
}

// Add constructor
$code .= <<< 'END_PHP'
    /**
     * Service Constructor
     *
     * @param string $s_WSDL    The location of the WSDL file.
     * @param array  $a_Options Any additional parameters to add to the service.
     */
    public function __construct($s_WSDL = Null, array $a_Options = array())
    {
        /**
         * Use the optional WSDL file location if it is supplied.
         */
        $s_WSDL = is_null($s_WSDL) ? #LOCALISED_WSDL# : $s_WSDL;

        /**
         * Add the classmap to the options.
         */
        foreach (self::$a_ClassMap as $s_ServiceClassName => $s_MappedClassName) {
            if (!isset($a_Options['classmap'][$s_ServiceClassName])) {
                $a_Options['classmap'][$s_ServiceClassName] = $s_MappedClassName;
            }
        }

        parent::__construct($s_WSDL, $a_Options);
    }


END_PHP;

// Build __get() and __isset() if there are any protected properties and build the strings for the header docblock.
if (!empty($a_ProtectedProperties)) {
    ksort($a_ProtectedProperties);
    $s_ProtectedProperties = implode(
        PHP_EOL,
        array_map(
            function ($a_ProtectedProperty) {
                return "            case '{$a_ProtectedProperty['Property']}' :";
            },
            $a_ProtectedProperties
        )
    );
    $i_MaxLenType          = max(
        array_map(
            function ($a_ProtectedProperty) use ($service) {
                return strlen($service['types'][substr($a_ProtectedProperty['Property'], 2)]['map']);
            },
            $a_ProtectedProperties
        )
    );
    $i_MaxLenProperty      = max(
        array_map(
            function ($a_ProtectedProperty) {
                return strlen($a_ProtectedProperty['Property']);
            },
            $a_ProtectedProperties
        )
    );
    $s_ReadOnlyProperties  = implode(
            PHP_EOL,
            array_map(
                function ($a_ProtectedProperty) use ($service, $i_MaxLenType, $i_MaxLenProperty) {
                    $s_ProtectedPropertyName = substr($a_ProtectedProperty['Property'], 2);

                    return ' * @property-read ' . str_pad(
                        $service['types'][$s_ProtectedPropertyName]['map'],
                        $i_MaxLenType + 1
                    ) . '$' . str_pad(
                        $a_ProtectedProperty['Property'],
                        $i_MaxLenProperty
                    ) . " {$a_ProtectedProperty['Label']}";
                },
                $a_ProtectedProperties
            )
        ) . PHP_EOL;

    $code .= <<< 'END_PHP'
    /**
     * Getter for protected properties.
     *
     * @param string $s_Property
     */
    public function __get($s_Property)
    {
        switch ($s_Property) {
#PROTECTED_PROPERTIES#
                $m_Result = $this->$s_Property;
                break;
            default :
                $m_Result = parent::__get($s_Property);
        }

        return $m_Result;
    }

    /**
     * Isseter for protected properties.
     *
     * @param string $s_Property
     */
    public function __isset($s_Property)
    {
        switch ($s_Property) {
#PROTECTED_PROPERTIES#
                $b_Result = isset($this->$s_Property);
                break;
            default :
                $m_Result = parent::__isset($s_Property);
        }

        return $b_Result;
    }


END_PHP;
}

//Add call proxy and soap header assignment logic.
$code .= <<< 'END_PHP'
    /**
     * Service call proxy.
     *
     * @param string       $s_ServiceName    The name of the service being called.
     * @param array        $a_Parameters     The parameters being supplied to the service.
     * @param SOAPHeader[] $a_RequestHeaders An array of SOAPHeaders.
     * @return mixed The service response.
     */
    protected function callProxy($s_ServiceName, array $a_Parameters = Null, array $a_RequestHeaders = Null)
    {
        $m_Result = $this->__soapCall($s_ServiceName, $a_Parameters, array(
            'uri'        => 'http://tempuri.org/',
            'soapaction' => '',
            ), (!empty($a_RequestHeaders) ? array_filter($a_RequestHeaders) : Null), $a_ResponseHeaders);

        if (!empty($a_ResponseHeaders)){
            foreach($a_ResponseHeaders as $s_HeaderName => $m_Data){
                $s_PropertyName = "o_{$s_HeaderName}";
                $this->$s_PropertyName = $m_Data;
            }
        }

        return $m_Result;
    }

    /**
     * Build and populate a SOAP header.
     *
     * @param string       $s_HeaderName The name of the services SOAP Header.
     * @param array|object $m_Data       Any data that can be mapped to the SOAP Header. Public properties of objects will be used if an object is supplied.
     * @param string       $s_Namespace  The namespace which will default to this service's namespace.
     * @return SoapHeader
     */
    public function assignSoapHeader($s_HeaderName, $m_Data = Null, $s_Namespace = self::SERVICE_NAMESPACE)
    {

        /**
         * Is there a corresponding property of this service for the requested SOAP Header?
         * Is there a mapped class for this SOAP Header?
         * Do we have any data to populate the SOAP Header with?
         */
        $s_LocalPropertyName = "o_{$s_HeaderName}";
        if (property_exists($this, $s_LocalPropertyName) && isset(self::$a_ClassMap[$s_HeaderName]) && !empty($m_Data)) {

            /**
             * Start with no data for the SOAP Header.
             */
            $a_HeaderData = Null;

            /**
             * Get the mapped class and get the properties defined for the SOAP Header.
             */
            $o_HeaderClass      = new ReflectionClass(self::$a_ClassMap[$s_HeaderName]);
            $a_HeaderProperties = $o_HeaderClass->getProperties();

            /**
             * Produce an array of public data from an object.
             */
            if (is_object($m_Data)) {
                $o_DataClass      = new ReflectionClass($m_Data);
                $a_DataProperties = $o_DataClass->getProperties(ReflectionProperty::IS_PUBLIC);
                $a_Data           = array();
                foreach ($a_DataProperties as $o_Property) {
                    $s_PropertyName          = $o_Property->name;
                    $a_Data[$s_PropertyName] = $m_Data->$s_PropertyName;
                }
            }
            else if (is_array($m_Data)) {
                $a_Data = $m_Data;
            }

            /**
             * Process the data as an array.
             */
            if (is_array($a_Data)) {
                foreach ($a_HeaderProperties as $o_Property) {
                    $s_PropertyName = $o_Property->name;
                    if (isset($a_Data[$s_PropertyName])) {
                        $a_HeaderData[$s_PropertyName] = $a_Data[$s_PropertyName];
                    }
                }
            }

            /**
             * Build the SOAP Header and assign it the corresponding property.
             */
            $this->$s_LocalPropertyName = new SoapHeader($s_Namespace, $s_HeaderName, $a_HeaderData);
        }
    }


END_PHP;
$code = str_replace(
    '#LOCALISED_WSDL#',
    is_file($s_WSDL) ? "__DIR__ . '/{$service['class']}/{$service['class']}.wsdl'" : "'{$s_WSDL}'",
    $code
);
$code = str_replace('#READ_ONLY_PROPERTIES#', $s_ReadOnlyProperties, $code);
if (!empty($s_ProtectedProperties)) {
    $code = str_replace('#PROTECTED_PROPERTIES#', $s_ProtectedProperties, $code);
}

echo 'Adding Methods', PHP_EOL;
foreach ($service['functions'] as $function) {
    echo " - {$function['name']}", PHP_EOL;
    $code .= "    /**" . PHP_EOL;
    if (!!$function['doc']) {
        $code .= parse_doc("     * ", $function['doc']);
        $code .= "     *" . PHP_EOL;
    }

    $signature = []; // used for function signature
    $para      = []; // just variable names
    if (count($function['params']) > 0) {
        foreach ($function['params'] as $param) {
            $code .= "     * @param " . (isset($param[0]) && isset($service['types'][$param[0]]['map']) ? $service['types'][$param[0]]['map'] : $param[0]) . " " . (isset($param[1]) ? $param[1] : '') . "\n";
            $signature[] = (in_array($param[0], $primitive_types) or substr(
                    $param[0],
                    0,
                    7
                ) == 'ArrayOf') ? $param[1] : (isset($param[0]) ? $service['types'][$param[0]]['map'] : '') . " " . (isset($param[1]) ? $param[1] : '');
            $para[]      = $param[1];
        }
    }
    $code .= "     * @return {$service['types'][$function['return']]['map']}" . PHP_EOL;

    if (!empty($function['headers_in'])) {
        $code .= "     *" . PHP_EOL;
        $code .= "     * This service call may use the following SOAPHeaders:" . PHP_EOL;
        foreach ($function['headers_in'] as $s_Header) {
            $code .= "     * @SOAPHeaderRequest {$service['types'][$s_Header]['map']}" . PHP_EOL;
        }
    }

    if (!empty($function['headers_out'])) {
        $code .= "     *" . PHP_EOL;
        $code .= "     * This service call's response may contain the following SOAPHeaders:" . PHP_EOL;
        foreach ($function['headers_out'] as $i_Header => $s_Header) {
            $code .= "     * @SOAPHeaderResponse {$service['types'][$s_Header]['map']}" . PHP_EOL;
        }
    }

    if (!empty($function['faults'])) {
        $code .= "     *" . PHP_EOL;
        $code .= "     * This service call may generate the following Service Level Faults:" . PHP_EOL;
        foreach ($function['faults'] as $i_Fault => $s_Fault) {
            $code .= "     * @SOAPFault {$service['types'][$s_Fault]['map']}" . PHP_EOL;
        }
    }

    $code .= "     */\n";
    $code .= "    public function {$function['name']}(" . implode(', ', $signature) . ")\n    {\n";
    $code .= "        return \$this->callProxy('{$function['method']}', array(";
    $params = [];
    if (count($signature) > 0) { // add arguments
        foreach ($signature as $param) {
            if (strpos($param, ' ')) { // slice
                $tmp_param = explode(' ', $param);
                $param     = array_pop($tmp_param);
            }
            $params[] = $param;
        }
        $code .= implode(', ', $params);
    }
    $code .= ")";
    if (!empty($function['headers_in'])) {
        if (!empty($function['headers_in'])) {
            $code .= ', array(' . PHP_EOL;
            foreach ($function['headers_in'] as $s_Header) {
                $code .= '                $this->o_' . $s_Header . ',' . PHP_EOL;
            }
            $code .= '        )';
        }
    }

    $code .= ");\n    }\n\n";
}
$code .= "}\n";

echo "Writing {$service['class']}.php";
$s_ClassFileName = 'Services/' . str_replace('_', '/', $service['class']) . '.php';
if (!!($s_CodeHeader = classHeader($s_Processed, $s_WSDL, md5($code), $s_ClassFileName))) {
    @mkdir(dirname($s_ClassFileName), 0755, true);
    file_put_contents($s_ClassFileName, "<?php" . PHP_EOL . PHP_EOL . $s_CodeHeader . $code);
    echo PHP_EOL;
} else {
    echo ' - Unchanged', PHP_EOL;
}

echo 'Saving WSDL file', PHP_EOL;
$dom->preserveWhiteSpace = false;
$dom->formatOutput       = true;
$s_SavedWSDL             = 'Services/' . str_replace('_', '/', $service['class']) . "/{$service['class']}.wsdl";
$dom->save($s_SavedWSDL);
echo " - {$s_SavedWSDL}", PHP_EOL, 'Finished', PHP_EOL;

//print_r($service);

function classHeader($s_Processed, $s_WSDLURL, $s_MD5, $s_ClassFileName)
{
    $s_WSDL2PHP = date('r', filemtime(__FILE__));
    $s_WSDLURL  = is_file($s_WSDLURL) ? substr($s_WSDLURL, 1 + strlen(__DIR__)) : $s_WSDLURL;
    $s_Result   = <<< END_DOCBLOCK
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
        function ($line)
        use ($width, $prefix) {
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
        function ($line)
        use ($prefix) {
            return $prefix . $line;
        },
        $lines
    );

    // Join
    $doc = implode("\n", $lines);

    return $doc . "\n";
}

/**
 * Look for enumeration
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

    for ($i = 0; $i < $enum_list->length; $i++) {
        $value        = $enum_list->item(
            $i
        )->attributes->getNamedItem('value')->nodeValue;
        $value_node   = $enum_list->item(
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
 * Look for a type
 *
 * @param DOM    $dom
 * @param string $class
 *
 * @return DOMNode
 */
function findType(&$dom, $class)
{
    $types_node  = $dom->getElementsByTagName('types')->item(0);
    $schema_list = $types_node->getElementsByTagName('schema');

    for ($i = 0; $i < $schema_list->length; $i++) {
        $children = $schema_list->item($i)->childNodes;
        for ($j = 0; $j < $children->length; $j++) {
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
        $s = 'value_' . $s;
    }
    if (in_array(strtolower($s), $reserved_keywords)) {
        $s = '_' . $s;
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
