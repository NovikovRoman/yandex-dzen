<?php

namespace YandexDzen;

class StatisticPublication
{
    private $feedShows;
    private $shows;
    private $likes;
    private $views;
    private $viewsTillEnd;
    private $sumViewTimeSec;

    public function __construct($data)
    {
        $this->feedShows = empty($data['feedShows']) ? 0 : (int)$data['feedShows'];
        $this->shows = empty($data['shows']) ? 0 : (int)$data['shows'];
        $this->likes = empty($data['likes']) ? 0 : (int)$data['likes'];
        $this->views = empty($data['views']) ? 0 : (int)$data['views'];
        $this->viewsTillEnd = empty($data['viewsTillEnd']) ? 0 : (int)$data['viewsTillEnd'];
        $this->sumViewTimeSec = empty($data['sumViewTimeSec']) ? 0 : (int)$data['sumViewTimeSec'];
    }

    public function getShows()
    {
        return $this->shows;
    }

    public function getFeedShows()
    {
        return $this->feedShows;
    }

    public function getLikes()
    {
        return $this->likes;
    }

    public function getViews()
    {
        return $this->views;
    }

    public function getViewsTillEnd()
    {
        return $this->viewsTillEnd;
    }

    public function getSumViewTimeSec()
    {
        return $this->sumViewTimeSec;
    }
}