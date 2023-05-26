<?php

namespace In2code\GbEvents\Domain\Repository;

use DateTime;
use In2code\GbEvents\Domain\Model\Event;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\AndInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\ConstraintInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\OrInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Query;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Repository for In2code\GbEvents\Domain\Model\Event
 */
class EventRepository extends Repository
{
    /**
     * Find all events between $startDate and $stopDate
     *
     * @param DateTime $startDate
     * @param DateTime $stopDate
     * @param bool $showStartedEvents
     * @param string $categories
     * @return array
     */
    public function findAllBetween(
        DateTime $startDate,
        DateTime $stopDate,
        $showStartedEvents = false,
        $categories = null
    ) {
        $query = $this->queryAllBetween($startDate, $stopDate, $showStartedEvents, $categories);

        return $this->resolveRecurringEvents(
            $query->execute(),
            $grouped = true,
            $startDate,
            $stopDate,
            $showStartedEvents
        );
    }

    /**
     * Find all events (limited to an amount of years)
     * Filter can also be set to one day with &tx_gbevents_main[filter][day]=2022-11-30
     *
     * @param int $years
     * @param bool $showStartedEvents
     * @param string $categories
     * @return array
     */
    public function findAll(
        $years = 1,
        $showStartedEvents = false,
        $categories = null,
        array $filter = [],
        $limitTimeRangeStart = null,
        $limitTimeRangeEnd = null
    ) {
        if ((int)$years === 0) {
            $years = 1;
        }

        $startDate = new DateTime('midnight');
        $stopDate = new DateTime(sprintf('midnight + %d years', (int)$years));
        $timeRangeBegin = $limitTimeRangeStart ? $this->timestampToDatetime($limitTimeRangeStart) : null;
        $timeRangeEnd = $limitTimeRangeEnd ? $this->timestampToDatetime($limitTimeRangeEnd) : null;

        if (isset($filter['day'])) {
            try {
                $startDate = new DateTime($filter['day']);
                $stopDate = clone $startDate;
                $stopDate->modify('+1 day')->modify('-1 second');
            } catch (Throwable $exception) {
                // Don't manipulate dates
            }
        }

        $query = $this->queryAllBetween($startDate, $stopDate, $showStartedEvents, $categories);
        $result = $query->execute();
        $items = $this->resolveRecurringEvents(
            $result,
            $grouped = false,
            $startDate,
            $stopDate,
            $showStartedEvents
        );

        if ($limitTimeRangeStart || $limitTimeRangeEnd) {
            $events = [];
            foreach ($items as $key => $item) {
                $eventTimeStamp = $item->getEventDate()->getTimestamp();
                if ($limitTimeRangeStart && $limitTimeRangeEnd) {
                    if ($eventTimeStamp >= (int) $limitTimeRangeStart
                        && $eventTimeStamp <= (int) $limitTimeRangeEnd
                    ) {
                        $events[$key] = $item;
                    }
                } elseif ($limitTimeRangeStart) {
                    if ($eventTimeStamp >= (int) $limitTimeRangeStart) {
                        $events[$key] = $item;
                    }
                } elseif ($limitTimeRangeEnd) {
                    if ($eventTimeStamp <= (int) $limitTimeRangeEnd) {
                        $events[$key] = $item;
                    }
                }
            }
        } else {
            $events = $items;
        }
        unset($items);

        return $events;
    }

    /**
     * Find upcoming events (limited to a count of n)
     *
     * @param int $limit
     * @param bool $showStartedEvents
     * @param string $categories
     * @return array
     */
    public function findUpcoming(
        $limit = 3,
        $showStartedEvents = false,
        $categories = null
    ) {
        if ((int)$limit === 0) {
            $limit = 3;
        }

        $startDate = new DateTime('midnight');
        $stopDate = new DateTime('midnight + 5 years');

        $query = $this->createQuery();
        $query->setOrderings(['event_date' => QueryInterface::ORDER_ASCENDING]);
        /** @var ConstraintInterface $conditions */
        $conditions = $query->greaterThanOrEqual('event_date', $startDate);
        $this->applyRecurringConditions($query, $conditions, $startDate, $stopDate, $categories);

        return $this->resolveRecurringEvents(
            $query->execute(),
            $grouped = false,
            $startDate,
            $stopDate,
            $showStartedEvents,
            $limit
        );
    }

    /**
     * Find past events (limited to a count of n)
     *
     * @param int $limit
     * @param string $categories
     * @return array
     */
    public function findBygone(
        $limit = 3,
        $categories = null,
        $limitTimeRangeStart = null,
        $limitTimeRangeEnd = null
    ) {
        if ((int)$limit === 0) {
            $limit = 3;
        }

        $startDate = new DateTime('midnight - 20 years');
        $stopDate = new DateTime('midnight - 1 second');
        $cutOffDate = new DateTime($limit . ' years ago midnight');
        $timeRangeBegin = $limitTimeRangeStart ? $this->timestampToDatetime($limitTimeRangeStart) : null;
        $timeRangeEnd = $limitTimeRangeEnd ? $this->timestampToDatetime($limitTimeRangeEnd) : null;

        $query = $this->createQuery();
        $query->setOrderings(['event_date' => QueryInterface::ORDER_ASCENDING]);
        /** @var ConstraintInterface $conditions */
        $conditions = $query->greaterThanOrEqual('event_date', $startDate);
        $this->applyRecurringConditions($query, $conditions, $startDate, $stopDate, $categories);
        $result = $query->execute();

        $items = array_filter(
            $this->resolveRecurringEvents($result, $grouped = false, $startDate, $stopDate, true, 0, true),
            function (Event $event) use (&$cutOffDate, &$stopDate) {
                return $event->getEventDate() >= $cutOffDate && $event->getEventDate() <= $stopDate;
            }
        );
        usort(
            $items,
            function (Event $a, Event $b) {
                return strcmp($a->getEventDate()->getTimestamp(), $b->getEventDate()->getTimestamp());
            }
        );
        if ($limitTimeRangeStart || $limitTimeRangeEnd) {
            $events = [];
            foreach ($items as $key => $item) {
                $eventTimeStamp = $item->getEventDate()->getTimestamp();
                if ($limitTimeRangeStart && $limitTimeRangeEnd) {
                    if ($eventTimeStamp >= (int) $limitTimeRangeStart
                     && $eventTimeStamp <= (int) $limitTimeRangeEnd
                    ) {
                        $events[$key] = $item;
                    }
                } elseif ($limitTimeRangeStart) {
                    if ($eventTimeStamp >= (int) $limitTimeRangeStart) {
                        $events[$key] = $item;
                    }
                } elseif ($limitTimeRangeEnd) {
                    if ($eventTimeStamp <= (int) $limitTimeRangeEnd) {
                        $events[$key] = $item;
                    }
                }
            }
        } else {
            $events = $items;
        }
        unset($items);

        return array_reverse($events);
    }

    protected function timestampToDatetime ($timestamp) : ?\DateTime
    {
        if ($timestamp) {
            $dateTime = new \DateTime();
            // If you must have use time zones
            // $dateTime = new \DateTime('now', new \DateTimeZone('Europe/Helsinki'));
            $dateTime->setTimestamp($timestamp);
            // echo $date->format($datetimeFormat);
            return $dateTime;
        }
        return null;
    }

    /**
     * Add conditions to retrieve recurring dates from the database
     *
     * @param QueryInterface|Query $query
     * @param ConstraintInterface $conditions
     * @param DateTime $startDate
     * @param DateTime $stopDate
     * @param string $categories
     */
    protected function applyRecurringConditions(
        QueryInterface &$query,
        ConstraintInterface $conditions,
        DateTime $startDate,
        DateTime $stopDate,
        $categories = null,
        DateTime $timeRangeBegin = null,
        DateTime $timeRangeEnd = null
    ) {
        $conditions = $query->logicalOr([
            $conditions,
            $query->logicalAnd([
                $query->lessThanOrEqual('event_date', $stopDate),
                $query->logicalOr([
                    $query->greaterThan('recurringDays', 0),
                    $query->greaterThan('recurringWeeks', 0)
                ]),
                $query->logicalOr([
                    $query->equals('recurringStop', 0),
                    $query->greaterThanOrEqual('recurringStop', $startDate)
                ])
            ])
        ]);
        $this->applyCategoryFilters($query, $conditions, $categories);
        $query->matching($conditions);
    }

    /**
     * Add conditions to filter the selected records by category
     *
     * @param QueryInterface|Query $query
     * @param ConstraintInterface $conditions
     * @param array $categories
     */
    protected function applyCategoryFilters(
        QueryInterface $query,
        ConstraintInterface &$conditions,
        $categories
    ) {
        $categories = GeneralUtility::intExplode(',', $categories, true);
        if (is_array($categories) && !empty($categories)) {
            $categoryConditions = array_map(
                function ($category) use ($query) {
                    return $query->contains('categories', $category);
                },
                $categories
            );
            $conditions = $query->logicalAnd([
                $conditions,
                $query->logicalOr($categoryConditions)
            ]);
        }
    }

    /**
     * Resolve the recurring events into current dates honoring start and stopdates as well as limits
     * on the amount of dates returned
     *
     * @param QueryResultInterface $events
     * @param bool $grouped
     * @param DateTime $startDate
     * @param DateTime $stopDate
     * @param bool $checkDuration
     * @param int $limit
     * @param bool $archive
     * @return array
     */
    protected function resolveRecurringEvents(
        QueryResultInterface $events,
        $grouped,
        DateTime $startDate,
        DateTime $stopDate,
        $checkDuration = false,
        $limit = 0,
        $archive = false
    ) {
        $today = new DateTime('midnight today');
        $days = [];

        foreach ($events as $event) {
            foreach ($event->getEventDates($startDate, $stopDate, $grouped) as $eventDate) {
                if ($archive) {
                    if ($grouped === false) {
                        if ($checkDuration === false && !$this->isEventInPast($eventDate)) {
                            continue;
                        }
                        if ($checkDuration === true && !$this->isEventInPast($eventDate, $event->getDuration())) {
                            continue;
                        }
                    } else {
                        if (!$this->isEventInPast($eventDate, 0, $startDate)) {
                            continue;
                        }
                    }
                    $recurringEvent = clone $event;
                    $recurringEvent->setEventDate($eventDate);

                    if ($grouped) {
                        $days[$eventDate->format('Y-m-d')]['events'][$event->getUid()] = $recurringEvent;
                    } else {
                        if ($recurringEvent->getEventStopDate() <= $today) {
                            $days[$eventDate->format('Y-m-d') . '_' . $event->getUniqueIdentifier()] =
                                $recurringEvent;
                        }
                    }
                } else {
                    if ($grouped === false) {
                        if ($checkDuration === false && !$this->isVisibleEvent($eventDate)) {
                            continue;
                        }
                        if ($checkDuration === true && !$this->isVisibleEvent($eventDate, $event->getDuration())) {
                            continue;
                        }
                    } else {
                        if (!$this->isVisibleEvent($eventDate, 0, $startDate)) {
                            continue;
                        }
                    }
                    $recurringEvent = clone $event;
                    $recurringEvent->setEventDate($eventDate);

                    if ($grouped) {
                        $days[$eventDate->format('Y-m-d')]['events'][$event->getUid()] = $recurringEvent;
                    } else {
                        if ($recurringEvent->getEventStopDate() >= $today) {
                            $days[$eventDate->format('Y-m-d') . '_' . $event->getUniqueIdentifier()] =
                                $recurringEvent;
                        }
                    }
                }
            }
        }
        ksort($days);

        if ((int)$limit !== 0) {
            $days = array_slice($days, 0, $limit, true);
        }

        return $days;
    }

    /**
     * Check if the event is active at the given point in time
     *
     * @param DateTime $eventDate
     * @param int $duration
     * @param DateTime $currentDate
     * @return bool
     */
    protected function isVisibleEvent(
        DateTime $eventDate,
        $duration = 0,
        DateTime $currentDate = null
    ) {
        if (is_null($currentDate)) {
            $currentDate = new DateTime('midnight');
        }
        return ($eventDate->getTimestamp() + $duration) >= $currentDate->getTimestamp();
    }

    /**
     * @param DateTime $eventStart
     * @param int $duration
     * @param DateTime|null $currentDate
     * @return bool
     */
    protected function isEventInPast(
        DateTime $eventStart,
        $duration = 0,
        DateTime $currentDate = null
    ) {
        if (is_null($currentDate)) {
            $currentDate = new DateTime('midnight');
        }

        if ($eventStart->getTimestamp() + $duration <= $currentDate->getTimestamp()) {
            return true;
        }

        return false;
    }

    /**
     * Returns query constraints for simple events.
     *
     * @param QueryInterface|Query $query
     * @param DateTime $startDate
     * @param DateTime $stopDate
     * @param bool $showStartedEvents
     * @return AndInterface|OrInterface
     */
    protected function getBaseConditions(
        QueryInterface &$query,
        DateTime $startDate,
        DateTime $stopDate,
        $showStartedEvents = false
    ) {
        $conditions = $query->logicalAnd([
            $query->greaterThanOrEqual('event_date', $startDate),
            $query->lessThanOrEqual('event_date', $stopDate)
        ]);
        if ($showStartedEvents == true) {
            $conditions = $query->logicalOr([
                $conditions, $query->logicalAnd([
                    $query->lessThanOrEqual('event_date', $startDate),
                    $query->greaterThanOrEqual('event_stop_date', $startDate)
                ])
            ]);
        }

        return $conditions;
    }

    /**
     * Find all events between $startDate and $stopDate
     *
     * @param DateTime $startDate
     * @param DateTime $stopDate
     * @param bool $showStartedEvents
     * @param null $categories
     * @return QueryInterface
     */
    protected function queryAllBetween(
        DateTime $startDate,
        DateTime $stopDate,
        $showStartedEvents = false,
        $categories = null
    ) {
        $query = $this->createQuery();
        $query->setOrderings(['event_date' => QueryInterface::ORDER_ASCENDING]);
        $conditions = $this->getBaseConditions($query, $startDate, $stopDate, $showStartedEvents);
        $this->applyRecurringConditions($query, $conditions, $startDate, $stopDate, $categories);

        return $query;
    }
}
