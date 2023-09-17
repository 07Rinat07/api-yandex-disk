<?php


function vardump($str)
{
    echo "<pre>";
    var_dump($str);
    echo "</pre>";
}

class Backup
{

//    http://localhost/api-yandex-disk/#access_token=y0_AgAAAABw2XocAAqB4AAAAADs5Zp5oOuKeqfyQZKsnd8SH7WzBNbtioY&token_type=bearer&expires_in=31536000

//. https://oauth.yandex.ru/authorize?response_type=token&client_id=y0_AgAAAABw2XocAAqB4AAAAADs5Zp5oOuKeqfyQZKsnd8SH7WzBNbtioY
    protected $token = 'y0_AgAAAABw2XocAAqB4AAAAADs5Zp5oOuKeqfyQZKsnd8SH7WzBNbtioY';

    /**
     * Method sendQueryYaDisk
     *
     * @param string $urlQuery URL для отправки запросов
     * @param array $arrQuery массив параметров
     * @param string $methodQuery метод отправки
     *
     * @return array
     */
    public function sendQueryYaDisk(string $urlQuery, array $arrQuery = [], string $methodQuery = 'GET'): array
    {
        if ($methodQuery == 'POST') {
            $fullUrlQuery = $urlQuery;
        } else {
            $fullUrlQuery = $urlQuery . '?' . http_build_query($arrQuery);
        }

        $ch = curl_init($fullUrlQuery);
        switch ($methodQuery) {
            case 'PUT':
                curl_setopt($ch, CURLOPT_PUT, true);
                break;

            case 'POST':
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($arrQuery));
                break;

            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: OAuth ' . $this->token]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $resultQuery = curl_exec($ch);
        curl_close($ch);

        return (!empty($resultQuery)) ? json_decode($resultQuery, true) : [];
    }

    /**
     * Метод для получения общей информации об аккаунте
     *
     * @return array
     */
    public function disk_getInfo(): array
    {
        $urlQuery = 'https://cloud-api.yandex.net/v1/disk/';
        return $this->sendQueryYaDisk($urlQuery);
    }


    /**
     * Получение директорий
     *
     * @param array $arrParams параметры для получения ресурсов
     * @param string $typeDir тип области ресурсов
     *
     * @return array
     */
    public function disk_resources(array $arrParams, string $typeDir = ''): array
    {
        switch ($typeDir) {
            case 'trash':
                /* запрос для директорий в корзине */
                $urlQuery = 'https://cloud-api.yandex.net/v1/disk/trash/resources';
                break;

            default:
                /* запрос для активных директорий */
                $urlQuery = 'https://cloud-api.yandex.net/v1/disk/resources';
                break;
        }

        return $this->sendQueryYaDisk($urlQuery, $arrParams);
    }

    /**
     * Получение плоского списка всех файлов
     *
     * @param array $arrParams параметры для получения ресурсов
     *
     * @return array
     */
    public function disk_resources_files(array $arrParams = []): array
    {
        $urlQuery = 'https://cloud-api.yandex.net/v1/disk/resources/files';
        return $this->sendQueryYaDisk($urlQuery, $arrParams);
    }

    /**
     * Получение последних загруженных элементов
     *
     * @param array $arrParams параметры для получения ресурсов
     *
     * @return array
     */
    public function disk_resources_last_uploaded(array $arrParams = []): array
    {
        $urlQuery = 'https://cloud-api.yandex.net/v1/disk/resources/last-uploaded';
        return $this->sendQueryYaDisk($urlQuery, $arrParams);
    }


    /**
     * Метод для создания директории
     *
     * @param array $arrParams параметры для отправки запроса
     *
     * @return array
     */
    public function disk_resources_create_dir(array $arrParams): array
    {
        $urlQuery = 'https://cloud-api.yandex.net/v1/disk/resources/';
        return $this->sendQueryYaDisk($urlQuery, $arrParams, 'PUT');
    }

    /**
     * Метод для загрузки файлов
     *
     * @param string $filePath путь до файла
     * @param string $dirPath путь до директории на Яндекс.Диск
     *
     * @return string
     */
    public function disk_resources_upload(string $filePath, string $dirPath = ''): string
    {
        /* отправляем запрос на получение ссылки для загрузки */
        $arrParams = [
            'path' => $dirPath . basename($filePath),
            'overwrite' => 'true',
        ];

        $urlQuery = 'https://cloud-api.yandex.net/v1/disk/resources/upload';
        $resultQuery = $this->sendQueryYaDisk($urlQuery, $arrParams);
        /* ----------------- */

        if (empty($resultQuery['error'])) {
            /* Если ошибки нет, то отправляем файл на полученный URL. */
            $fp = fopen($filePath, 'r');

            $ch = curl_init($resultQuery['href']);
            curl_setopt($ch, CURLOPT_PUT, true);
            curl_setopt($ch, CURLOPT_UPLOAD, true);
            curl_setopt($ch, CURLOPT_INFILESIZE, filesize($filePath));
            curl_setopt($ch, CURLOPT_INFILE, $fp);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $http_code;
        } else {
            return $resultQuery['message'];
        }
    }

    /**
     * Метод для скачивания файлов на сервера
     *
     * @param string $filePath путь до файла в Яндекс.Диске
     * @param string $dirPath путь до директории на сервере
     *
     * @return array
     */
    public function disk_resources_download(string $filePath, string $dirPath = ''): array
    {
        /* отправляем запрос на получение ссылки для скачивания */
        $arrParams = [
            'path' => $filePath,
        ];

        $urlQuery = 'https://cloud-api.yandex.net/v1/disk/resources/download';
        $resultQuery = $this->sendQueryYaDisk($urlQuery, $arrParams);
        /* ----------------- */

        if (empty($resultQuery['error'])) {
            $file_name = $dirPath . basename($filePath);
            $file = @fopen($file_name, 'w');

            $ch = curl_init($resultQuery['href']);
            curl_setopt($ch, CURLOPT_FILE, $file);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: OAuth ' . $this->token));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            $resultQuery = curl_exec($ch);
            curl_close($ch);

            fclose($file);

            return [
                'message' => 'Файл успешно загружен',
                'path' => $file_name,
            ];
        } else {
            return $resultQuery;
        }
    }

}

$backupClass = new Backup();



$filePath = '/uploads/section-about-main.jpg';
$dirPath = $_SERVER['DOCUMENT_ROOT'] . '/public/';


$resultQuery = $backupClass->disk_resources_download($filePath, $dirPath);

vardump($resultQuery);