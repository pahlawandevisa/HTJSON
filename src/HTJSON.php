<?php

/**
 * Created by PhpStorm.
 * User: Bfriedman
 * Date: 3/3/17
 * Time: 8:02 PM
 */
class HTJSON
{
    /**
     * Separator between tags
     *
     * @var string
     */
    private static $SEPARATOR = "\n";

    /**
     * Encodes the given html and returns JSON
     *
     * @param string $html  HTML to parse
     * @return array
     */
    public static function encodeHTML($html)
    {
        $parsed = [];

        // normalize our html
        $html = self::normalizeHTML($html);

        // break up further
        $htmlParts = explode("\n", $html);

        // array of currently active entries
        $activeEntries = [];

        $lineNumber = 0;

        // whether to parse raw rather than html
        $ignoreParse = false;

        foreach($htmlParts as $part) {

            $lineNumber++;

            // attempt to match each type of part
            if(preg_match("/<!DOCTYPE ([^>]+)>/i", $part, $matches) && !$ignoreParse) {
                // DOCTYPE
                $parsed[] = [
                    'tag'   => '!DOCTYPE',
                    'properties'   => [
                        $matches[1]
                    ],
                    'type'  => 'self-closing'
                ];

            } else if(preg_match("/^<\s*([a-z0-9]+)(\s+.*)?\/>$/i", $part, $matches) && !$ignoreParse) {
                // Self Closing Tag
                if(count($matches) >= 3) {
                    // possible properties
                    $properties = self::parseProperties($matches[2]);

                } else {
                    // no properties
                    $properties = [];

                }

                $entry = [
                    'tag' => $matches[1],
                    'properties' => $properties,
                    'type' => 'self-closing'
                ];

                if(count($activeEntries) > 0) {
                    // add to last entry
                    $activeEntries[count($activeEntries)-1]['content'][] = $entry;

                } else {
                    // add straight on
                    $parsed[] = $entry;

                }

            } else if(preg_match("/^<\s*([a-z0-9]+)(\s+[^>]*)?(?<!\/)>$/i", $part, $matches) && !$ignoreParse) {
                // Normal Tag
                if(count($matches) >= 3) {
                    // possible properties
                    $properties = self::parseProperties($matches[2]);

                } else {
                    // no properties
                    $properties = [];

                }

                $tag = strtolower($matches[1]);

                $entry = [
                    'tag'           => $tag,
                    'properties'    => $properties,
                ];

                if(self::isTagVoid($tag)) {
                    // void tag, add as is
                    $entry['type']  = 'void';
                    if(count($activeEntries) > 0) {
                        // add to last entry
                        $activeEntries[count($activeEntries)-1]['content'][] = $entry;

                    } else {
                        // add straight on
                        $parsed[] = $entry;

                    }


                } else {
                    // normal element
                    $entry['type']      = 'normal';
                    $entry['content']   = [];

                    // push to our active list
                    array_push($activeEntries, $entry);

                }

                if($tag == 'style' || $tag == 'script') {
                    // temporarily disable deep parsing until we close this tag
                    $ignoreParse = true;

                }

            } else if(preg_match("/^<\/\s*([a-z0-9]+)(\s+[^>]*)?\s*>$/i", $part, $closingMatches) || preg_match("/^-->/", $part)) {
                // Closing Tag

                if(isset($closingMatches) && count($closingMatches) > 0) {
                    if(strtolower($closingMatches[1]) == 'br' || strtolower($closingMatches[1]) == 'hr') {
                        // correct this self closing tag
                        // TODO COPIED FROM ABOVE
                        // Self Closing Tag
                        if(count($closingMatches) >= 3) {
                            // possible properties
                            $properties = self::parseProperties($closingMatches[2]);

                        } else {
                            // no properties
                            $properties = [];

                        }

                        $entry = [
                            'tag' => $closingMatches[1],
                            'properties' => $properties,
                            'type' => 'self-closing'
                        ];

                        if(count($activeEntries) > 0) {
                            // add to last entry
                            $activeEntries[count($activeEntries)-1]['content'][] = $entry;

                        } else {
                            // add straight on
                            $parsed[] = $entry;

                        }
                        // TODO COPIED FROM ABOVE

                        continue;


                    }
                }

                // verify we have an active entry we can use
                if(count($activeEntries) == 0) {
                    print_r("<p style='margin:8px;padding:8px;color:#fff;background:#f06'>Unmatched closing tag found on line {$lineNumber}:\n".htmlentities($part)."</p>");

                }

                // pop last active entry
                $entry = array_pop($activeEntries);

                if(isset($closingMatches) && count($closingMatches) > 0) {
                    // verify this tag name matches
                    if ($entry['tag'] != strtolower($closingMatches[1]) && $entry['tag'] != '--comment--') {
                        // report an error, and ignore this tag for now...

                        print_r("<p style='margin:8px;padding:8px;color:#fff;background:#f06'>Soft Error: Tag mismatch for '{$entry['tag']}' on line {$lineNumber}:\n" . htmlentities($part) . "</p>");

                        // push our entry back, and add this 'rubbish'
                        array_push($activeEntries, $entry);

                        // add 'rubbish'
                        $entry = [
                            'tag' => '--rubbish--',
                            'type' => 'rubbish',
                            'content'   => $part
                        ];

                        if(count($activeEntries) > 0) {
                            // add to last entry
                            $activeEntries[count($activeEntries)-1]['content'][] = $entry;

                        } else {
                            // add straight on
                            $parsed[] = $entry;

                        }


                    } else if($entry['tag'] == '--comment--') {
                        // push our entry back and continue adding as 'junk'
                        array_push($activeEntries, $entry);

                        // Not a tag. Add to the last active entry
                        if (!isset($activeEntries[count($activeEntries) - 1]['contents'])) {
                            $activeEntries[count($activeEntries) - 1]['contents'] = "";

                        }
                        $activeEntries[count($activeEntries) - 1]['contents'] .= $part;

                    }

                } else if($entry['tag'] != '--comment--') {
                    print_r("<p style='margin:8px;padding:8px;color:#fff;background:#f06'>Comment mismatch for '{$entry['tag']}' on line {$lineNumber}:\n" . htmlentities($part) . "</p>");
                    // TODO REMOVE
                    die(htmlentities(json_encode($activeEntries)));

                }

                // add to our parsed data
                if (count($activeEntries) > 0) {
                    // add to last entry
                    $activeEntries[count($activeEntries) - 1]['content'][] = $entry;

                } else {
                    // add straight on
                    $parsed[] = $entry;

                }

                if(
                    $ignoreParse &&
                    $entry['tag'] == 'script' ||
                    $entry['tag'] == 'style' ||
                    $entry['tag'] == '--comment--'
                ) {
                    // resume normal parsing again
                    $ignoreParse = false;

                }


            } else if(trim($part) != "") {

                if(preg_match("/<!--/", $part) && !$ignoreParse) {
                    // add an open 'comment' tag
                    array_push($activeEntries, [
                        'tag'           => '--comment--',
                        'properties'    => [],
                        'content'       => "",
                        'type'          => 'normal' // normal tag
                    ]);

                    // indicate we are now ignoring standard parsing rules until we close out...
                    $ignoreParse = true;

                } else {
                    // Not a tag. Add to the last active entry
                    if (!isset($activeEntries[count($activeEntries) - 1]['contents'])) {
                        $activeEntries[count($activeEntries) - 1]['contents'] = "";

                    }
                    $activeEntries[count($activeEntries) - 1]['contents'] .= $part;

                }

            }

        }

        return $parsed;

    }

    /**
     * Decodes the given data and returns a document
     *
     * @param array $data
     * @return string
     */
    public static function decodeHTML(array $data)
    {
        return self::_decode($data);

    }

    /**
     * Internally decodes part of a site from json
     *
     * @param array $data   Data to decode
     * @return string
     * @throws Exception
     */
    private static function _decode(array $data)
    {
        $contents = "";

        foreach ($data as $entry) {
            // decode this part

            if (!isset($entry['type'])) {
                throw new Exception("Missing type for entry: " . json_encode($entry));

            }

            $type = $entry['type'];

            // decode properties
            $properties = isset($entry['properties']) ? self::decodeProperties($entry['properties']) : "";

            // check for raw contents
            $raw = isset($entry['contents']) ? $entry['contents']."\n" : '';

            if ($type == 'normal') {

                if(!isset($entry['content'])) {
                    throw new Exception("Missing content for entry: ".json_encode($entry));

                }
                $content = $entry['content'];

                $tag = $entry['tag'];

                if(is_array($content)) {
                    // decode
                    $content = self::_decode($content);

                }

                if($tag != '--comment--') {
                    // normal
                    $contents .= "<{$tag}{$properties}>".self::$SEPARATOR. $content ."{$raw}</{$tag}>".self::$SEPARATOR;

                } else {
                    // comment
                    $contents .= "<!--".self::$SEPARATOR. $content ."{$raw}-->".self::$SEPARATOR;

                }

            } else if ($type == 'self-closing') {
                // self closing
                $contents .= "<{$entry['tag']}{$properties}/>".self::$SEPARATOR;

            } else if($type == 'void') {
                // void
                $contents .= "<{$entry['tag']}{$properties}>".self::$SEPARATOR;

            } else if ($type == 'rubbish') {
                // garbage
                if(!isset($entry['content'])) {
                    throw new Exception("Missing content for entry: ".json_encode($entry));

                }
                $content = $entry['content'].self::$SEPARATOR;

                $contents .= $content;

            } else {
                throw new Exception("Unknown type passed '{$type}'");

            }


        }


        return $contents;

    }

    /**
     * Decodes a set of properties to return
     *
     * @param array $properties Properties to decode
     * @return string
     */
    private static function decodeProperties(array $properties)
    {
        $propertiesString = "";
        foreach($properties as $key => $value) {
            if(is_int($key)) {
                // add only value
                $propertiesString.=" {$value}";

            } else {
                $propertiesString .= ' ' . $key . '="' . $value . '"';

            }

        }

        return $propertiesString;

    }

    private static function isTagVoid($tag)
    {
        return (
            $tag == 'input' ||
            $tag == 'meta' ||
            $tag == 'img' ||
            $tag == 'area' ||
            $tag == 'base' ||
            $tag == 'br' ||
            $tag == 'col' ||
            $tag == 'embed' ||
            $tag == 'hr' ||
            $tag == 'keygen' ||
            $tag == 'link' ||
            $tag == 'param' ||
            $tag == 'source' ||
            $tag == 'track' ||
            $tag == 'wbr'
            //     area, base, br, col, embed, hr, img, input, keygen, link, meta, param, source, track, wbr
        );

    }

    /**
     * Parses properties and returns an array of key/value pairs
     *
     * @param string $properties    Properties to parse
     * @return array
     */
    private static function parseProperties($properties)
    {
        $properties = trim($properties);

        if($properties == "") {
            // no properties, return empty
            return [];

        }

        // normalize properties
        $properties = preg_replace("/([-a-z0-9_]+)\s*=\s*('|\")/", "\n$1\n$2", $properties);

        $properties = trim($properties);

        $properties = explode("\n", $properties);

        $pairs = [];

        for($x = 0; $x < count($properties); $x+=2) {
            $key    = trim($properties[$x]);
            $value  = trim($properties[$x+1]);

            // add this pair, trimming of edges of this value
            $pairs[$key] = substr($value, 1, -1);

        }

        return $pairs;

    }

    private static function normalizeHTML($html)
    {
        // normalize breaks after tags
        $html = preg_replace("/\\n\\n/m", "\n", $html);
        $html = preg_replace("/>\\n/m", ">", $html);

        // clean up whitespace
        $html = preg_replace("/>\s*</", "><", $html);

        // restore line breaks
        $html = preg_replace("/>/", ">\n", $html);
        $html = preg_replace("/([^\\n])</", "$1\n<", $html);

        // remove whitespace after a break
        $html = preg_replace("/\\n\s*/m", "\n", $html);

        // normalize comments
        $html = preg_replace("/\\n?<!--\\n?/", "\n<!--\n", $html);
        $html = preg_replace("/\\n?-->\\n?/", "\n-->\n", $html);

        return $html;

    }
}