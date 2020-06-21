<?php

namespace App\Utils;

/**
 * Class FileUtils
 * @package App\Utils
 */
class FileUtils
{
    const USERNAMES_FILE    = 'usernames_example.txt';
    const ACCOUNTS_FILE     = 'accounts.json';

    /**
     * Get array of random instagram usernames from data file
     *
     * @param int|null $limit
     *
     * @return array
     */
    public static function getUsernamesExample(int $limit = null)
    {
        $names = explode(PHP_EOL, self::getContents(self::USERNAMES_FILE));
        shuffle($names);
        if ($limit) {
            if ($limit > count($names)) {
                $limit = count($names);
            }
            $names = array_slice($names, 0, $limit);
        }
        return $names;
    }

    /**
     * Get array data from json file
     *
     * @param string $fileName
     *
     * @return array
     */
    public static function getArrayDataFromJsonFile(string $fileName)
    {
        $data = json_decode(self::getContents($fileName), true);
        return $data ? $data : [];
    }

    /**
     * Get contents of the file
     *
     * @param string $fileName
     *
     * @return string|bool
     */
    public static function getContents(string $fileName)
    {
        $path = __DIR__.'/../../data/' . $fileName;
        self::createFileIfNeed($path);
        return file_get_contents($path);
    }

    /**
     * Save array data in json file
     *
     * @param array     $data
     * @param string    $fileName
     *
     * @param string $path
     */
    public static function putJsonData(array $data, string $fileName)
    {
        $path = __DIR__.'/../../data/' . $fileName;
        self::createFileIfNeed($path);
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Check file exists by path
     *
     * @param string $path
     */
    public static function createFileIfNeed(string $path)
    {
        if (!file_exists($path)) {
            file_put_contents($path, '');
        }
    }
}