<?php

namespace app\components;

use DateTime;

class LogParser
{
    /**
     * Парсинг одной строки nginx лога
     *
     * @param string $row
     */
    public static function parse(string $row)
    {
        $pattern = '/^(\S+) - - \[([^\]]+)\] "([^"]*)" \d+ \d+ "([^"]*)" "([^"]*)"$/';

        if (!preg_match($pattern, $row, $matches))
            return null;

        $request = explode(' ', $matches[3]);
        $url = isset($request[1]) ? $request[1] : '';

        $userAgent = self::parseUserAgent($matches[5]);
        return [
            'ip' => $matches[1],
            'datetime' => self::parseDate($matches[2]),
            'url' => $url,
            'user_agent' => $matches[5],
            'os' => $userAgent['os'],
            'browser' => $userAgent['browser'],
            'architecture' => $userAgent['architecture']
        ];
    }

    /**
     * Парсинг даты
     *
     * @param string $dateString
     *
     * @return string|null
     */
    private static function parseDate(string $dateString): string|null
    {
        $date = DateTime::createFromFormat('d/M/Y:H:i:s O', $dateString);
        return $date ? $date->format('Y-m-d H:i:s') : null;
    }

    /**
     * Парсинг UserAgent
     * @param string $data
     *
     * @return array
     */
    private static function parseUserAgent(string $data): array
    {
        $result = [
            'architecture' => 'Unknown',
        ];

        $result['os'] = match (true) {
            stripos($data, 'Windows NT 10.0') !== false => 'Windows 10',
            stripos($data, 'Windows NT 6.3') !== false  => 'Windows 8.1',
            stripos($data, 'Windows NT 6.2') !== false  => 'Windows 8',
            stripos($data, 'Windows NT 6.1') !== false  => 'Windows 7',
            stripos($data, 'Windows NT 6.0') !== false  => 'Windows Vista',
            stripos($data, 'Windows NT 5.1') !== false  => 'Windows XP',
            stripos($data, 'Mac OS X') !== false => 'MacOS',
            stripos($data, 'Linux') !== false => 'Linux',
            stripos($data, 'Android') !== false => 'Android',
            stripos($data, 'IPhone') !== false => 'IOS',
            default => 'Unknown',
        };

        $result['browser'] = match (true) {
            stripos($data, 'Edg/') !== false => 'Edge',
            stripos($data, 'OPR/') !== false => 'Opera',
            stripos($data, 'Firefox/') !== false => 'Firefox',
            stripos($data, 'Chrome/') !== false => 'Chrome',
            stripos($data, 'Safari/') !== false => 'Safari',
            stripos($data, 'MSIE') !== false => 'Internet Explorer',
            default => 'Unknown',
        };

        if (stripos($data, 'x86_64') !== false || stripos($data, 'x64') !== false || stripos($data, 'WOW64') !== false) {
            $result['architecture'] = 'x64';
        } elseif (stripos($data, 'x86') !== false || stripos($data, 'i686') !== false) {
            $result['architecture'] = 'x86';
        }

        return $result;
    }
}
