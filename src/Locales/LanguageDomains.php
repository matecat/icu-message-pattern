<?php

namespace Matecat\Locales;

/*
   this class manages supported languages in the CAT tool
 */

use Exception;
use RuntimeException;

class LanguageDomains
{
    private static ?LanguageDomains $instance = null;

    /** @var array<int, array{key: string, display: string}> */
    private static array $subjectMap = [];

    /** @var array<string, string> */
    private static array $subjectHashMap = [];

    //access singleton
    public static function getInstance(): LanguageDomains
    {
        if (!self::$instance) {
            self::$instance = new LanguageDomains();
        }

        return self::$instance;
    }

    /**
     * @throws Exception
     */
    private function __construct()
    {
        //get languages file
        //
        // SDL supported language codes
        // http://kb.sdl.com/kb/?ArticleId=2993&source=Article&c=12&cid=23#tab:homeTab:crumb:7:artId:4878

        $file = __DIR__ . '/languageDomains.json';

        $string = file_get_contents($file);
        // @codeCoverageIgnoreStart
        if ($string === false) {
            throw new RuntimeException("Failed to read language domains from $file");
        }
        // @codeCoverageIgnoreEnd

        //parse to an associative array
        /** @var array<int, array{key: string, display: string}> $subjects */
        $subjects = json_decode($string, true, 512, JSON_THROW_ON_ERROR);

        self::$subjectMap = $subjects;

        array_walk(self::$subjectMap, function (array $element): void {
            self::$subjectHashMap[$element['key']] = $element['display'];
        });
    }

    /**
     * Get a list of language domains
     *
     * @return array<int, array{key: string, display: string}>
     */
    public static function getEnabledDomains(): array
    {
        return self::$subjectMap;
    }

    /**
     * @return array<string, string>
     */
    public static function getEnabledHashMap(): array
    {
        return self::$subjectHashMap;
    }

}
