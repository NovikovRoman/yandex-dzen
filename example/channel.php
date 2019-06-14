<?php

use GuzzleHttp\Exception\GuzzleException;
use YandexDzen\Client;
use YandexDzen\YandexDzen;

require_once __DIR__ . '/../vendor/autoload.php';
$filepath = __DIR__ . '/statistics_channel.csv';

$login = 'yandex_login';
$pass = 'password';
$profileName = 'profileName';

if (!file_exists($filepath)) {
    $fh = fopen($filepath, 'a+');
    $data = [
        'Дата',
        'Количество подписчиков',
        'Количество материалов',
        'Количество прямых показов',
        'Количество показов',
        'Количество просмотров',
        'Количество дочитываний',
        'Количество дочитываний, у которых ср. время прочитывания материала менее 45 секунд',
        'Количество лайков',
    ];
    $data = array_map(function ($item) {
        return iconv('utf-8', 'cp1251', $item);
    }, $data);
    fputcsv($fh, $data);
} else {
    $fh = fopen($filepath, 'a+');
}

try {
    $client = new Client($login, $pass);
    $client->auth();
    $yandexDzen = new YandexDzen($profileName, $client);
    $yandexDzen->getAllPublications();
    $data = array_map(function ($item) {
        return iconv('utf-8', 'cp1251', $item);
    }, $yandexDzen->generalStatistics());
    fputcsv($fh, $data);
} catch (Exception $e) {
    print_r($e->getMessage());
} catch (GuzzleException $e) {
    print_r($e->getMessage());
}
fclose($fh);