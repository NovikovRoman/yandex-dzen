<?php

namespace YandexDzen;

class CollectionPublications
{
    private $collection;
    private $sort;

    public function __construct()
    {
        $this->collection = [];
        $this->sort = [];
    }

    public function add(Publication $publication)
    {
        if (!$this->has($publication)) {
            $this->collection[$publication->getId()] = $publication;
            $this->sort[] = $publication->getId();
        }
        return $this;
    }

    public function has(Publication $publication)
    {
        return !empty($this->collection[$publication->getId()]);
    }

    public function count()
    {
        return count($this->sort);
    }

    public function getLastPublication()
    {
        if (empty($this->sort)) {
            return null;
        }
        return $this->collection[$this->sort[count($this->sort) - 1]];
    }

    public function numberShows()
    {
        $num = 0;
        /** @var Publication $publication */
        foreach ($this->collection as $publication) {
            $statistics = $publication->getStatistics();
            $num += $statistics->getShows();
        }
        return $num;
    }

    public function numberFeedShows()
    {
        $num = 0;
        /** @var Publication $publication */
        foreach ($this->collection as $publication) {
            $statistics = $publication->getStatistics();
            $num += $statistics->getFeedShows();
        }
        return $num;
    }

    public function numberLikes()
    {
        $num = 0;
        /** @var Publication $publication */
        foreach ($this->collection as $publication) {
            $statistics = $publication->getStatistics();
            $num += $statistics->getLikes();
        }
        return $num;
    }

    public function numberViews()
    {
        $num = 0;
        /** @var Publication $publication */
        foreach ($this->collection as $publication) {
            $statistics = $publication->getStatistics();
            $num += $statistics->getViews();
        }
        return $num;
    }

    public function numberViewsTillEnd()
    {
        $num = 0;
        /** @var Publication $publication */
        foreach ($this->collection as $publication) {
            $statistics = $publication->getStatistics();
            $num += $statistics->getViewsTillEnd();
        }
        return $num;
    }

    public function averageViewsTillEnd($lessSec = 45)
    {
        $num = 0;
        /** @var Publication $publication */
        foreach ($this->collection as $publication) {
            $statistics = $publication->getStatistics();
            if ($statistics->getViewsTillEnd()) {
                $averageReadingTime = round($statistics->getSumViewTimeSec() / $statistics->getViewsTillEnd());
                if ($lessSec > $averageReadingTime) {
                    $num += $statistics->getViewsTillEnd();
                }
            }
        }
        return $num;
    }
}