<?php

namespace YandexDzen;

use \GuzzleHttp\Exception\GuzzleException;
use \Exception;
use YandexDzen\Client as YandexDzenClient;

class YandexDzen
{
    const URL_PAGE = 'https://zen.yandex.ru/profile/editor/';
    const URL_NEXT_PAGE = 'https://zen.yandex.ru/media-api/get-publications-by-state?state=published&publisherId={publisherId}&pageSize=200&publicationIdAfter={lastPublicationId}';
    const URL_FIRST_PUBLICATIONS = 'https://zen.yandex.ru/media-api/get-publications-by-state?state=published&publisherId={publisherId}&pageSize=200';
    const URL_COUNTED_PUBLICATIONS = 'https://zen.yandex.ru/media-api/count-publications-by-state?state=published&publisherId={publisherId}';

    private $client;

    /** @var CollectionPublications */
    private $collectionPublications;
    private $publicationsCount;
    private $favouritesCount;
    private $publisherId;

    /**
     * YandexDzen constructor.
     * @param $profile
     * @param YandexDzenClient $client
     * @throws GuzzleException
     * @throws YandexDzenException
     */
    public function __construct($profile, YandexDzenClient $client)
    {
        $this->client = $client;
        $html = $this->client->get(self::URL_PAGE . $profile);
        preg_match('/w\._data\s*=\s*({\s*".+});\s*w\._uatraits/sui', $html, $m);
        if (empty($m[1])) {
            $e = new YandexDzenException('Не удалось получить данные для инициализации.');
            $e->setHtml('<pre>' . print_r($html, true) . '</pre>');
            throw $e;
        }

        $initData = json_decode($m[1], true);
        if (empty($initData)) {
            $e = new YandexDzenException('Не удалось распарсить данные для инициализации.');
            $e->setHtml('<pre>' . print_r($m[1], true) . '</pre>');
            throw $e;
        }

        preg_match('/w\._csrfToken\s*=\s*([\'"])([0-9a-f:]+)\1/sui', $html, $m);
        if (empty($m[2])) {
            $e = new YandexDzenException('Не удалось получить csrf-токен.');
            throw $e;
        }

        $this->client->setToken($m[2]);
        $this->favouritesCount = $initData['userPublisher']['favouritesCount'];
        $this->publisherId = $initData['userPublisher']['id'];

        $this->publicationsCount = $this->getCountedPublicationsByState();
        $this->collectionPublications = new CollectionPublications();
        $this->addPublications($this->getPublications());

        return $this;
    }

    public function getPublisherId()
    {
        return $this->publisherId;
    }

    public function getFavouritesCount()
    {
        return $this->favouritesCount;
    }

    public function getPublicationsCount()
    {
        return $this->publicationsCount;
    }

    /**
     * @return bool
     * @throws Exception
     * @throws GuzzleException
     */
    public function getNextPage()
    {
        // yandex иногда криво считает общее кол-во статей
        if ($this->collectionPublications->count() >= $this->publicationsCount || $this->collectionPublications->count() >= $this->publicationsCount - 1) {
            return false;
        }

        $url = str_replace(
            '{publisherId}',
            $this->getPublisherId(),
            self::URL_NEXT_PAGE
        );
        /** @var Publication $publication */
        $publication = $this->collectionPublications->getLastPublication();
        if (!$publication) { // такой ситуации не должно быть в принципе
            throw new Exception('Пустая коллекция');
        }

        $url = str_replace('{lastPublicationId}', $publication->getId(), $url);
        $html = $this->client->get($url);
        $data = json_decode($html, true);
        $this->addPublications($data['publications']);
        return true;
    }

    public function getCollectionPublications()
    {
        return $this->collectionPublications;
    }

    /**
     * @return $this
     * @throws Exception
     * @throws GuzzleException
     */
    public function getAllPublications()
    {
        while ($this->getNextPage()) {
            // пока есть что получать
            continue;
        }
        return $this;
    }

    public function numberPublicationsReceived()
    {
        return $this->collectionPublications->count();
    }

    public function generalStatistics()
    {
        return [
            'date' => date('d.m.Y H:i:s'),
            'favouritesCount' => $this->getFavouritesCount(),
            'publicationsCount' => $this->getPublicationsCount(),
            'shows' => $this->collectionPublications->numberShows(),
            'feedShows' => $this->collectionPublications->numberFeedShows(),
            'views' => $this->collectionPublications->numberViews(),
            'viewsTillEnd' => $this->collectionPublications->numberViewsTillEnd(),
            'averageViewsTillEnd' => $this->collectionPublications->averageViewsTillEnd(),
            'likes' => $this->collectionPublications->numberLikes(),
        ];
    }


    /**
     * @return array
     * @throws GuzzleException
     * @throws Exception
     */
    private function getPublications()
    {
        $url = str_replace(
            '{publisherId}',
            $this->getPublisherId(),
            self::URL_FIRST_PUBLICATIONS
        );
        $html = $this->client->get($url);

        $ar = json_decode($html, true);
        if (empty($ar['publications'])) {
            throw new Exception('Ошибочные данные: ' . $html);
        }

        return $ar['publications'];
    }

    /**
     * @return int
     * @throws GuzzleException
     * @throws Exception
     */
    private function getCountedPublicationsByState()
    {
        $url = str_replace(
            '{publisherId}',
            $this->getPublisherId(),
            self::URL_COUNTED_PUBLICATIONS
        );
        $html = $this->client->get($url);

        $ar = json_decode($html, true);
        if (!isset($ar['count'])) {
            throw new Exception('Ошибочные данные: ' . $html);
        }

        return $ar['count'];
    }

    private function addPublications($publications)
    {
        foreach ($publications as $item) {
            $publication = new Publication($item);
            $this->collectionPublications->add($publication);
        }
        return $this;
    }
}