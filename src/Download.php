<?php
/**
 * Класс занимает скачивание указанных конечных файлов.
 * По умолчанию скачивание присходит во многопоточном режиме
 * Created by Andrey Stepanenko.
 * User: webnitros
 * Date: 25.04.2022
 * Time: 11:51
 *
 * // Скачиваем
 * $Download = new Download();
 *
 * $files = [
 * 'source' => '',
 * 'target' => '',
 * ];
 *
 * foreach ($files as $item) {
 * $Download->addFile($item['source'], $item['source']);
 * }
 *
 * $results = null;
 * // Скачивание целой директории
 * if ($files = $Download->getFiles()) {
 * // Разрешаем выкачивать по 20 файлов одновременно
 * $limit = 20;
 * $files = $Download->splitArray($files);
 * foreach ($files as $array) {
 * $results = $Download->aSyncRequest($array, true, $limit);
 * }
 * }
 */

namespace MultiDownloadFile;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use MultiDownloadFile\Exceptions\ExceptionMultiDownloadFile;

class Download
{
    protected ?array $files = null;
    protected ?string $target = null;


    public function addFile(string $source, string $target)
    {
        $this->files[] = [
            'source' => $source,
            'target' => $target
        ];
    }

    public function getFiles()
    {
        return !$this->files ? null : $this->files;
    }

    public function resetFiles()
    {
        $this->files = null;
    }

    /**
     * Разбивка на чанки
     * @param array $urls
     * @param int $limit
     * @return array
     */
    public function splitArray(array $urls, $limit = 20)
    {
        $urls = array_chunk($urls, $limit);
        return $urls;
    }

    public function getResults()
    {
        return $this->results;
    }

    public function aSyncRequest(array $urls, $exception = true, $limit = 20)
    {
        if (count($urls) > $limit) {
            throw new Exception('Максимальное количество скачиваемых изображений за 1 раз ' . $limit . ' шт');
        }
        $config = [
            'verify' => false,
            'timeout' => 30.0,

        ];
        $this->client = new \GuzzleHttp\Client($config);
        $downloads = [];
        foreach ($urls as $file) {
            $downloads[] = [
                'source' => $file['source'],
                'target' => $file['target']
            ];
        }

        $promises = [];
        foreach ($downloads as $k => $data) {
            $source = $data['source'];
            $target = $data['target'];
            if (file_exists($target)) {
                // Удаляем для безопасности
                unlink($target);
            }
            $promises[] = $this->client->getAsync($source, ['sink' => $target]);
        }


        // Дождемся завершения запросов, даже если некоторые из них завершатся неудачно
        $results = Promise\settle($promises)->wait();
        foreach ($results as $k => $result) {
            $data = $downloads[$k];
            $target = $data['target'];
            $source = $data['source'];

            // Записываем состояние
            $state = $result['state'];
            $downloads[$k]['state'] = $state;

            if ($exception) {

                /* @var \GuzzleHttp\Psr7\Response $Response */
                if (array_key_exists('value', $result)) {
                    $Response = @$result['value'];
                    $code = $Response->getStatusCode();
                    if ($result['state'] !== 'fulfilled') {
                        throw new ExceptionMultiDownloadFile('Не удалось скачать изображение: status ' . $code . $source);
                    }
                    if ($code !== 200) {
                        throw new ExceptionMultiDownloadFile('Error download ' . $source);
                    }
                }

                if (array_key_exists('reason', $result)) {
                    $Response = $result['reason'];
                    throw new ExceptionMultiDownloadFile('Error download ' . $Response->getMessage());
                }

                if (!file_exists($target)) {
                    throw new ExceptionMultiDownloadFile('Изображение не загружено ' . $target . ' state ' . $state);
                }
            }
            $this->results[$target] = $state;

        }

        return $downloads;
    }
}
