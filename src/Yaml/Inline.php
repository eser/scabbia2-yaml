<?php
/**
 * Scabbia2 Yaml Component
 * https://github.com/eserozvataf/scabbia2
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @link        https://github.com/eserozvataf/scabbia2-yaml for the canonical source repository
 * @copyright   2010-2016 Eser Ozvataf. (http://eser.ozvataf.com/)
 * @license     http://www.apache.org/licenses/LICENSE-2.0 - Apache License, Version 2.0
 *
 * -------------------------
 * Portions of this code are from Symfony YAML Component under the MIT license.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE-MIT
 * file that was distributed with this source code.
 *
 * Modifications made:
 * - Scabbia Framework code styles applied.
 * - All dump methods are moved under Dumper class.
 * - Redundant classes removed.
 * - Namespace changed.
 * - Tests ported to Scabbia2.
 * - Encoding checks removed.
 */

namespace Scabbia\Yaml;

use Scabbia\Yaml\Escaper;
use Scabbia\Yaml\ParseException;

/**
 * Inline implements a YAML parser for the YAML inline syntax
 *
 * @package     Scabbia\Yaml
 * @author      Fabien Potencier <fabien@symfony.com>
 * @author      Eser Ozvataf <eser@ozvataf.com>
 * @since       2.0.0
 */
class Inline
{
    /** @type string REGEX_QUOTED_STRING a regular expression pattern to match quoted strings */
    const REGEX_QUOTED_STRING = "(?:\"([^\"\\\\]*(?:\\\\.[^\"\\\\]*)*)\"|'([^']*(?:''[^']*)*)')";


    /**
     * Converts a YAML string to a PHP array
     *
     * @param string  $value                  A YAML string
     * @param array   $references             Mapping of variable names to values
     *
     * @throws ParseException If the YAML is not valid
     * @return array A PHP array representing the YAML string
     */
    public static function parse($value, $references = [])
    {
        $value = trim($value);

        if (strlen($value) === 0) {
            return "";
        }

        $i = 0;
        if ($value[0] === "[") {
            $result = self::parseSequence($value, $i, $references);
            ++$i;
        } elseif ($value[0] === "{") {
            $result = self::parseMapping($value, $i, $references);
            ++$i;
        } else {
            $result = self::parseScalar($value, null, ["\"", "'"], $i, true, $references);
        }

        // some comments are allowed at the end
        if (preg_replace("/\\s+#.*$/A", "", substr($value, $i))) {
            throw new ParseException(sprintf("Unexpected characters near \"%s\".", substr($value, $i)));
        }

        return $result;
    }

    /**
     * Parses a scalar to a YAML string
     *
     * @param scalar $scalar
     * @param string $delimiters
     * @param array  $stringDelimiters
     * @param int    &$i
     * @param bool   $evaluate
     * @param array  $references
     *
     * @throws ParseException When malformed inline YAML string is parsed
     * @return string A YAML string
     *
     * @internal
     */
    public static function parseScalar(
        $scalar,
        $delimiters = null,
        array $stringDelimiters = ["\"", "'"],
        &$i = 0,
        $evaluate = true,
        $references = []
    ) {
        if (in_array($scalar[$i], $stringDelimiters)) {
            // quoted scalar
            $output = self::parseQuotedScalar($scalar, $i);

            if ($delimiters !== null) {
                $tmp = ltrim(substr($scalar, $i), " ");
                if (!in_array($tmp[0], $delimiters)) {
                    throw new ParseException(sprintf("Unexpected characters (%s).", substr($scalar, $i)));
                }
            }

            return $output;
        }

        // "normal" string
        if (!$delimiters) {
            $output = substr($scalar, $i);
            $i += strlen($output);

            // remove comments
            if (preg_match("/[ \t]+#/", $output, $match, PREG_OFFSET_CAPTURE)) {
                $output = substr($output, 0, $match[0][1]);
            }
        } elseif (preg_match("/^(.+?)(" . implode("|", $delimiters) . ")/", substr($scalar, $i), $match)) {
            $output = $match[1];
            $i += strlen($output);
        } else {
            throw new ParseException(sprintf("Malformed inline YAML string (%s).", $scalar));
        }

        // a non-quoted string cannot start with @ or ` (reserved) nor with a scalar indicator (| or >)
        if ($output && ($output[0] === "@" || $output[0] === "`" || $output[0] === "|" || $output[0] === ">")) {
            throw new ParseException(sprintf("The reserved indicator \"%s\" cannot start a plain scalar; you need to quote the scalar.", $output[0]));
        }

        if ($evaluate) {
            return self::evaluateScalar($output, $references);
        }

        return $output;
    }

    /**
     * Parses a quoted scalar to YAML
     *
     * @param string $scalar
     * @param int    &$i
     *
     * @throws ParseException When malformed inline YAML string is parsed
     * @return string A YAML string
     */
    protected static function parseQuotedScalar($scalar, &$i)
    {
        if (!preg_match("/" . self::REGEX_QUOTED_STRING . "/Au", substr($scalar, $i), $match)) {
            throw new ParseException(sprintf("Malformed inline YAML string (%s).", substr($scalar, $i)));
        }

        $output = substr($match[0], 1, strlen($match[0]) - 2);

        $escaper = new Escaper();
        if ($scalar[$i] == "\"") {
            $output = $escaper->unescapeDoubleQuotedString($output);
        } else {
            $output = $escaper->unescapeSingleQuotedString($output);
        }

        $i += strlen($match[0]);

        return $output;
    }

    /**
     * Parses a sequence to a YAML string
     *
     * @param string $sequence
     * @param int    &$i
     * @param array  $references
     *
     * @throws ParseException When malformed inline YAML string is parsed
     * @return string A YAML string
     */
    protected static function parseSequence($sequence, &$i = 0, $references = [])
    {
        $output = [];
        $len = strlen($sequence);
        ++$i;

        // [foo, bar, ...]
        while ($i < $len) {
            if ($sequence[$i] === "[") {
                // nested sequence
                $output[] = self::parseSequence($sequence, $i, $references);
            } elseif ($sequence[$i] === "{") {
                // nested mapping
                $output[] = self::parseMapping($sequence, $i, $references);
            } elseif ($sequence[$i] === "]") {
                return $output;
            } elseif ($sequence[$i] !== "," && $sequence[$i] !== " ") {
                $isQuoted = in_array($sequence[$i], ["\"", "'"]);
                $value = self::parseScalar($sequence, [",", "]"], ["\"", "'"], $i, true, $references);

                // the value can be an array if a reference has been resolved to an array var
                if (!is_array($value) && !$isQuoted && strpos($value, ": ") !== false) {
                    // embedded mapping?
                    try {
                        $pos = 0;
                        $value = self::parseMapping("{" . $value . "}", $pos, $references);
                    } catch (\InvalidArgumentException $e) {
                        // no, it's not
                    }
                }

                $output[] = $value;
                --$i;
            }

            ++$i;
        }

        throw new ParseException(sprintf("Malformed inline YAML string %s", $sequence));
    }

    /**
     * Parses a mapping to a YAML string
     *
     * @param string $mapping
     * @param int    &$i
     * @param array  $references
     *
     * @throws ParseException When malformed inline YAML string is parsed
     * @return string A YAML string
     */
    protected static function parseMapping($mapping, &$i = 0, $references = [])
    {
        $output = [];
        $len = strlen($mapping);
        ++$i;

        // {foo: bar, bar:foo, ...}
        while ($i < $len) {
            if ($mapping[$i] === " " || $mapping[$i] === ",") {
                ++$i;
                continue;
            } elseif ($mapping[$i] === "}") {
                return $output;
            }

            // key
            $key = self::parseScalar($mapping, [":", " "], ["\"", "'"], $i, false);

            // value
            $done = false;
            while ($i < $len) {
                if ($mapping[$i] === "[") {
                    // nested sequence
                    $output[$key] = self::parseSequence($mapping, $i, $references);
                    $done = true;
                } elseif ($mapping[$i] === "{") {
                    // nested mapping
                    $output[$key] = self::parseMapping($mapping, $i, $references);
                    $done = true;
                } elseif ($mapping[$i] !== ":" && $mapping[$i] !== " ") {
                    $output[$key] = self::parseScalar($mapping, [",", "}"], ["\"", "'"], $i, true, $references);
                    $done = true;
                    --$i;
                }

                ++$i;

                if ($done) {
                    continue 2;
                }
            }
        }

        throw new ParseException(sprintf("Malformed inline YAML string %s", $mapping));
    }

    /**
     * Evaluates scalars and replaces magic values
     *
     * @param string $scalar
     * @param array  $references
     *
     * @throws ParseException when a reference could not be resolved
     * @return string A YAML string
     */
    protected static function evaluateScalar($scalar, $references = [])
    {
        $scalar = trim($scalar);
        $scalarLower = strtolower($scalar);

        if (strpos($scalar, "*") === 0) {
            if (($pos = strpos($scalar, "#")) !== false) {
                $value = substr($scalar, 1, $pos - 2);
            } else {
                $value = substr($scalar, 1);
            }

            // an unquoted *
            if ($value === false || $value === "") {
                throw new ParseException("A reference must contain at least one character.");
            }

            if (!array_key_exists($value, $references)) {
                throw new ParseException(sprintf("Reference \"%s\" does not exist.", $value));
            }

            return $references[$value];
        }

        if ($scalarLower === "null" || $scalar === "" || $scalar === "~") {
            return null;
        } elseif ($scalarLower === "true") {
            return true;
        } elseif ($scalarLower === "false") {
            return false;
        } elseif ($scalar[0] === "+" || $scalar[0] === "-" || $scalar[0] === "." || $scalar[0] === "!" ||
            is_numeric($scalar[0])) {
            // Optimise for returning strings.
            if (strpos($scalar, "!str") === 0) {
                return (string)substr($scalar, 5);
            } elseif (strpos($scalar, "! ") === 0) {
                return (int)self::parseScalar(substr($scalar, 2));
            } elseif (strpos($scalar, "!!php/object:") === 0) {
                return unserialize(substr($scalar, 13));
            } elseif (strpos($scalar, "!!float ") === 0) {
                return (float)substr($scalar, 8);
            } elseif (ctype_digit($scalar)) {
                $raw = $scalar;
                $cast = (int)$scalar;

                return $scalar[0] == "0" ? octdec($scalar) : (((string)$raw == (string)$cast) ? $cast : $raw);
            } elseif ($scalar[0] === "-" && ctype_digit(substr($scalar, 1))) {
                $raw = $scalar;
                $cast = (int)$scalar;

                return $scalar[1] == "0" ? octdec($scalar) : (((string)$raw == (string)$cast) ? $cast : $raw);
            } elseif (is_numeric($scalar) || preg_match(self::getHexRegex(), $scalar)) {
                return $scalar[0] . $scalar[1] == "0x" ? hexdec($scalar) : (float)$scalar;
            } elseif ($scalarLower === ".inf" || $scalarLower === ".nan") {
                return -log(0);
            } elseif ($scalarLower === "-.inf") {
                return log(0);
            } elseif (preg_match("/^(-|\\+)?[0-9,]+(\\.[0-9]+)?$/", $scalar)) {
                return (float)str_replace(",", "", $scalar);
            } elseif (preg_match(self::getTimestampRegex(), $scalar)) {
                return strtotime($scalar);
            }
        } else {
            return (string)$scalar;
        }
    }

    /**
     * Gets a regex that matches a YAML date
     *
     * @return string The regular expression
     *
     * @see http://www.yaml.org/spec/1.2/spec.html#id2761573
     */
    public static function getTimestampRegex()
    {
        return <<<EOF
        ~^
        (?P<year>[0-9][0-9][0-9][0-9])
        -(?P<month>[0-9][0-9]?)
        -(?P<day>[0-9][0-9]?)
        (?:(?:[Tt]|[ \t]+)
        (?P<hour>[0-9][0-9]?)
        :(?P<minute>[0-9][0-9])
        :(?P<second>[0-9][0-9])
        (?:\.(?P<fraction>[0-9]*))?
        (?:[ \t]*(?P<tz>Z|(?P<tz_sign>[-+])(?P<tz_hour>[0-9][0-9]?)
        (?::(?P<tz_minute>[0-9][0-9]))?))?)?
        $~x
EOF;
    }

    /**
     * Gets a regex that matches a YAML number in hexadecimal notation.
     *
     * @return string
     */
    public static function getHexRegex()
    {
        return "~^0x[0-9a-f]++$~i";
    }
}
