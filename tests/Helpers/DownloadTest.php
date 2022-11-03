<?php
/**
 * Created by Andrey Stepanenko.
 * User: webnitros
 * Date: 25.04.2022
 * Time: 11:53
 */

namespace Tests\Helpers;

use MultiDownloadFile\Download;
use Mockery;
use MultiDownloadFile\Response;
use Tests\TestCase;

class DownloadTest extends TestCase
{
    public function testGet()
    {
        /* @var Mockery|\MultiDownloadFile\Download $Download */
        // Загружаем заглущки

        $MockeryDownload = Mockery::mock(Download::class)
            ->makePartial(); // брать методы из родителя

        // Скачиваем
        $files = [
            [
                'source' => 'https://cdn.fandeco.ru/assets/images/products/237142/big/a4630pl-1wh.jpg', // Ссылка на файл откуда будет выкачиваться
                'target' => dirname(__FILE__, 1) . '/a4630pl-1wh.jpg', // Директория куда положить файл
            ]
        ];


        $MockeryDownload->shouldReceive('aSyncRequest')
            ->andReturn($files);


        foreach ($files as $item) {
            $MockeryDownload->addFile($item['source'], $item['source']);
        }

        $results = null;
        // Скачивание целой директории
        if ($files = $MockeryDownload->getFiles()) {
            // Разрешаем выкачивать по 20 файлов одновременно
            $limit = 20;
            $files = $MockeryDownload->splitArray($files);
            foreach ($files as $array) {
                $results = $MockeryDownload->aSyncRequest($array, true, $limit);
            }
        }

        self::assertIsArray($results);

    }
}
