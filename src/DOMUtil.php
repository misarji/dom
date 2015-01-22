<?php

namespace Chadicus;

use DOMDocument;
use DOMException;
use DOMNode;
use DOMText;
use DOMXPath;

/**
 * Static helper class for working with DOM objects.
 */
final class DOMUtil
{
    /**
     * Coverts the given array to a DOMDocument.
     *
     * @param array $array The array to covert.
     *
     * @return DOMDocument
     */
    public static function fromArray(array $array)
    {
        $document = new DOMDocument();
        foreach (self::flatten($array) as $path => $value) {
            self::addXPath($document, $path, $value);
        }

        return $document;
    }

    /**
     * Converts the given DOMDocument to an array.
     *
     * @param DOMDocument $document The document to convert.
     *
     * @return array
     */
    public static function toArray(DOMDocument $document)
    {
        $result = [];
        $domXPath = new DOMXPath($document);
        foreach ($domXPath->query('//*[not(*)] | //@*') as $node) {
            self::pathToArray($result, $node->getNodePath(), $node->nodeValue);
        }

        return $result;
    }

    /**
     * Helper method to add a new DOMNode to the given document with the given value.
     *
     * @param DOMDocument $document The document to which the node will be added.
     * @param string       $xpath    A valid xpath destination of the new node.
     * @param mixed        $value    The value for the new node.
     *
     * @return void
     *
     * @throws DOMException Thrown if the given $xpath is not valid.
     */
    private static function addXPath(DOMDocument $document, $xpath, $value = null)
    {
        $pointer = $document;
        $domXPath = new DOMXPath($document);

        if (@$domXPath->evaluate($xpath) === false) {
            throw new DOMException("XPath {$xpath} is not valid.");
        }

        foreach (array_filter(explode('/', $xpath)) as $tagName) {
            $matches = [];
            preg_match('/^(?P<name>[a-z][\w0-9-]*)\[(?P<count>\d+)\]$/i', $tagName, $matches);
            $tagName = array_key_exists('name', $matches) ? $matches['name'] : $tagName;
            $count = array_key_exists('count', $matches) ? (int)$matches['count'] : 1;

            if ($tagName[0] === '@') {
                $attribute = $document->createAttribute(substr($tagName, 1));
                $pointer->appendChild($attribute);
                $pointer = $attribute;
                continue;
            }

            $list = $domXPath->query($tagName, $pointer);
            self::addMultiple($document, $pointer, $tagName, $count - $list->length);

            $pointer = $domXPath->query($tagName, $pointer)->item($count - 1);
        }

        $pointer->nodeValue = $value;
    }

    /**
     * Helper method to add multiple identical nodes to the given context node.
     *
     * @param DOMDocument $document The parent document.
     * @param DOMNode     $context  The node to which the new elements will be added.
     * @param string      $tagName  The tag name of the element.
     * @param integer     $limit    The number of elements to create.
     *
     * @return void
     */
    private static function addMultiple(DOMDocument $document, DOMNode $context, $tagName, $limit)
    {
        for ($i = 0; $i < $limit; $i++) {
            $context->appendChild($document->createElement($tagName));
        }
    }

    /**
     * Helper method to create all sub elements in the given array based on the given xpath.
     *
     * @param array  $array The array to which the new elements will be added.
     * @param string $path  The xpath defining the new elements.
     * @param mixed  $value The value for the last child element.
     *
     * @return array
     */
    private static function pathToArray(array &$array, $path, $value = null)
    {
        $path = str_replace(['[', ']'], ['/', ''], $path);
        $parts = array_filter(explode('/', $path));
        $key = array_shift($parts);

        if (is_numeric($key)) {
            $key = (int)$key -1;
        }

        if (empty($parts)) {
            $array[$key] = $value;
            return;
        }

        if (!array_key_exists($key, $array)) {
            $array[$key] = [];
        } elseif (!is_array($array[$key])) {
            $array[$key] = [$array[$key]];
        }

        //RECURSION!!
        self::pathToArray($array[$key], implode('/', $parts), $value);
    }

    /**
     * Helper method to flatten a multi-dimensional array into a single dimensional array whose keys are xpaths.
     *
     * @param array  $array  The array to flatten.
     * @param string $prefix The prefix to recursively add to the flattened keys.
     *
     * @return array
     */
    private static function flatten(array $array, $prefix = '')
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_int($key)) {
                $newKey = (substr($prefix, -1) == ']') ? $prefix : "{$prefix}[" . (++$key) . ']';
            } else {
                $newKey = $prefix . (empty($prefix) ? '' : '/') . $key;
            }

            if (is_array($value)) {
                $result = array_merge($result, self::flatten($value, $newKey));
                continue;
            }

            $result[$newKey] = $value;
        }

        return $result;
    }
}
