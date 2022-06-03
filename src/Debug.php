<?php

namespace Src;

class Debug
{
    
    private static string $html = '';
    private static string $file_path;

    public static function initPath(string $file_path): void
    {

        self::$file_path = $file_path;
        
    }

    public static function rec(mixed $arr, string $name = ''): void
    {

        if ($name) {
            
            self::$html .= '<hr> [' . date('Y-m-d H:i:s') . '] ' . $name . PHP_EOL;
            
        }

        self::$html .= '<pre>' . print_r($arr, true) . '</pre>';

    }

    public static function recordToFile(bool $die = false): void
    {

        $index = fopen(self::$file_path, 'w');

        fwrite($index, self::$html);

        fclose($index);

        echo '[' . date('Y-m-d H:i:s') . '] Write to ' . self::$file_path . PHP_EOL;

        if($die) die;

    }
    
}