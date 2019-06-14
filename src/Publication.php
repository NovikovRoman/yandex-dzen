<?php

namespace YandexDzen;

use \DateTime;

class Publication
{
    private $id;
    private $title;
    private $addTime;
    private $itemId;
    private $type;
    private $snippet;
    private $snippetFrozen;
    private $hasPublished;
    private $hasChanges;
    private $modTime;
    /** @var StatisticPublication */
    private $statistics;

    public function __construct($data)
    {
        $this->title = $data['content']['preview']['title'];
        $this->id = $data['id'];
        $this->itemId = $data['itemId'];
        $this->addTime = empty($data['addTime']) ? null : (new DateTime())->setTimestamp($data['addTime']);
        $this->snippet = $data['content']['preview']['snippet'];
        $this->type = $data['content']['type'];
        $this->modTime = (new DateTime())->setTimestamp($data['content']['modTime']);
        $this->hasChanges = $data['privateData']['hasChanges'];
        $this->hasPublished = $data['privateData']['hasPublished'];
        $this->snippetFrozen = $data['privateData']['snippetFrozen'];
        $this->statistics = new StatisticPublication($data['privateData']['statistics']);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getStatistics()
    {
        return $this->statistics;
    }
}