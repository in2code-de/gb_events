<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011 Morton Jonuschat <m.jonuschat@gute-botschafter.de>, Gute Botschafter GmbH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * A single event
 */
class Tx_GbEvents_Domain_Model_Event extends Tx_Extbase_DomainObject_AbstractEntity {

  /**
   * The title of the event
   *
   * @var string
   * @validate NotEmpty
   */
  protected $title;

  /**
   * A short teaser text
   *
   * @var string
   */
  protected $teaser;

  /**
   * A detailed description of the event
   *
   * @var string
   * @validate NotEmpty
   */
  protected $description;

  /**
   * The location of the event
   *
   * @var string
   */
  protected $location;

  /**
   * The date when the event happens
   *
   * @var DateTime
   * @validate NotEmpty
   */
  protected $eventDate;

  /**
   * The time the event happens
   *
   * @var string
   */
  protected $eventTime;

  /**
   * The images for this event
   *
   * @var string
   */
  protected $images;

  /**
   * The downloads for this event
   *
   * @var string
   */
  protected $downloads;

  /**
   * The weeks of the month the event should occur at
   *
   * @var integer
   */
  protected $recurringWeeks;

  /**
   * The days of the week the event should occur at
   *
   * @var integer
   */
  protected $recurringDays;

  /**
   * The date until which a recurring event should repeat
   *
   * @var DateTime
   */
  protected $recurringStop;

  /**
   * The date when the event ends
   *
   * @var DateTime
   */
  protected $eventStopDate;


  /**
   * @param string $title
   * @return void
   */
  public function setTitle($title) {
    $this->title = $title;
  }

  /**
   * @return string
   */
  public function getTitle() {
    return $this->title;
  }

  /**
   * @param string $teaser
   * @return void
   */
  public function setTeaser($teaser) {
    $this->teaser = $teaser;
  }

  /**
   * @return string
   */
  public function getTeaser() {
    return $this->teaser;
  }

  /**
   * @param string $description
   * @return void
   */
  public function setDescription($description) {
    $this->description = $description;
  }

  /**
   * @return string
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * @param string $location
   * @return void
   */
  public function setLocation($location) {
    $this->location = $location;
  }

  /**
   * @return string
   */
  public function getLocation() {
    return $this->location;
  }

  /**
   * @param DateTime $eventDate
   * @return void
   */
  public function setEventDate(DateTime $eventDate) {
    $this->eventDate = $eventDate;
  }

  /**
   * This only returns the initial event date
   *
   * @return DateTime
   */
  public function getEventDate() {
    return $this->eventDate->modify('midnight');
  }

  /**
   * This returns the initial event dates including
   * all recurring events up to and including the
   * stopDate, taking the defined end of recurrance
   * into account
   *
   * @param DateTime $startDate
   * @param DateTime $stopDate
   */
  public function getEventDates(DateTime $startDate, DateTime $stopDate) {
    $monthNames = array('', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
    $oneDay = new DateInterval('P1D');
    $oneMonth = new DateInterval('P1M');

    $startMonth = clone($startDate);
    $startMonth->modify('first day of this month');
    $stopMonth = clone($stopDate);
    $stopMonth->modify('last day of this month');
    $recurringMonths = array();
    while($startMonth < $stopMonth) {
      $recurringMonths[] = clone($startMonth);
      $startMonth->add($oneMonth);
    }

    $recurringWeeks = $this->getRecurringWeeksAsText();
    $recurringDays = $this->getRecurringDaysAsText();
    $eventDates = array();
    foreach($recurringMonths as $workDate) {
      # Weeks have been selected, check every nth week / day combination
      if(count($recurringWeeks) !== 0) {
        foreach($this->getRecurringWeeksAsText() as $week) {
          foreach($this->getRecurringDaysAsText() as $day) {
            $workDate->modify(sprintf("%s %s of this month", $week, $day));
            if($workDate >= $this->getEventDate() && (is_null($this->getRecurringStop()) || $workDate <= $this->getRecurringStop()) && $workDate >= $startDate && $workDate <= $stopDate) {
              $eventDates[$workDate->format('Y-m-d')] = clone($workDate);
              $re_StartDate = clone($workDate);
              $difference = $this->getEventDate()->diff($re_StartDate);
              $re_StopDate = clone($this->getEventStopDate());
              $re_StopDate->add($difference);
              while($re_StartDate <= $re_StopDate) {
                $eventDates[$re_StartDate->format('Y-m-d')] = clone($re_StartDate);
                $re_StartDate->modify('+1 day');
              }
            }
          }
        }
      } else {
        # Check the weekdays only, ignoring the weeks of the month
        $stopDay = clone($workDate);
        $stopDay->modify('last day of this month');
        while($workDate <= $stopDay) {
          $addCurrentDay = FALSE;
          switch($workDate->format('w')) {
          case 0:
          case 7:
            $addCurrentDay = in_array('Sunday', $recurringDays);
            break;
          case 1:
            $addCurrentDay = in_array('Monday', $recurringDays);
            break;
          case 2:
            $addCurrentDay = in_array('Tuesday', $recurringDays);
            break;
          case 3:
            $addCurrentDay = in_array('Wednesday', $recurringDays);
            break;
          case 4:
            $addCurrentDay = in_array('Thursday', $recurringDays);
            break;
          case 5:
            $addCurrentDay = in_array('Friday', $recurringDays);
            break;
          case 6:
            $addCurrentDay = in_array('Saturday', $recurringDays);
            break;
          }
          if($addCurrentDay) {
            if($workDate >= $this->getEventDate() && (is_null($this->getRecurringStop()) || $workDate <= $this->getRecurringStop()) && $workDate >= $startDate && $workDate <= $stopDate) {
              $eventDates[$workDate->format('Y-m-d')] = clone($workDate);
              $re_StartDate = clone($workDate);
              $difference = $this->getEventDate()->diff($re_StartDate);
              $re_StopDate = clone($this->getEventStopDate());
              $re_StopDate->add($difference);
              while($re_StartDate <= $re_StopDate) {
                $eventDates[$re_StartDate->format('Y-m-d')] = clone($re_StartDate);
                $re_StartDate->modify('+1 day');
              }
            }
          }
          $workDate->add($oneDay);
        }
      }
    }
    $myStartDate = clone($this->getEventDate());
    $myStopDate = $this->getEventStopDate();
    while($myStartDate <= $myStopDate) {
      $eventDates[$myStartDate->format('Y-m-d')] = clone($myStartDate);
      $myStartDate->modify('+1 day');
    }

    $eventDates[$this->getEventDate()->format('Y-m-d')] = $this->getEventDate();
    ksort($eventDates);
    return $eventDates;
  }

  /**
   * @param string $eventTime
   * @return void
   */
  public function setEventTime($eventTime) {
    $this->eventTime = $eventTime;
  }

  /**
   * @return string
   */
  public function getEventTime() {
    return $this->eventTime;
  }

  /**
   * @param string $images
   * @return void
   */
  public function setImages($images) {
    $this->images = $images;
  }

  /**
   * @return array
   */
  public function getImages() {
    $mapFunc = create_function('$i', 'return "uploads/tx_gbevents/" . $i;');
    return array_map($mapFunc, t3lib_div::trimExplode(',', $this->images, TRUE));
  }

  /**
   * @param string $downloads
   * @return void
   */
  public function setDownloads($downloads) {
    $this->downloads = $downloads;
  }

  /**
   * @return array
   */
  public function getDownloads() {
    $mapFunc = create_function('$i', 'return array("file" => "uploads/tx_gbevents/" . $i, "name" => basename($i));');
    return array_map($mapFunc, t3lib_div::trimExplode(',', $this->downloads, TRUE));
  }

  /**
   * @param integer $recurringWeeks
   * @return void
   */
  public function setRecurringWeeks($recurringWeeks) {
    $this->recurringWeeks = $recurringWeeks;
  }

  /**
   * @return integer
   */
  public function getRecurringWeeks() {
    return $this->recurringWeeks;
  }

  /**
   * @return array
   */
  protected function getRecurringWeeksAsText() {
    $weeks = array();
    if($this->getRecurringWeeks() & 1) {
      $weeks[] = 'first';
    }
    if($this->getRecurringWeeks() & 2) {
      $weeks[] = 'second';
    }
    if($this->getRecurringWeeks() & 4) {
      $weeks[] = 'third';
    }
    if($this->getRecurringWeeks() & 8) {
      $weeks[] = 'fourth';
    }
    if($this->getRecurringWeeks() & 16) {
      $weeks[] = 'fifth';
    }
    if($this->getRecurringWeeks() & 32) {
      $weeks[] = 'last';
    }
    return $weeks;
  }

  /**
   * @param integer $recurringDays
   * @return void+
   */
  public function setRecurringDays($recurringDays) {
    $this->recurringDays = $recurringDays;
  }

  /**
   * @return integer
   */
  public function getRecurringDays() {
    return $this->recurringDays;
  }

  /**
   * @return array
   */
  protected function getRecurringDaysAsText() {
    $days = array();
    if($this->getRecurringDays() === 0 && $this->getRecurringWeeks() !== 0) {
      switch($this->getEventDate()->format('w')) {
      case 0:
      case 7:
        $days[] = 'Sunday';
        break;
      case 1:
        $days[] = 'Monday';
        break;
      case 2:
        $days[] = 'Tuesday';
        break;
      case 3:
        $days[] = 'Wednesday';
        break;
      case 4:
        $days[] = 'Thursday';
        break;
      case 5:
        $days[] = 'Friday';
        break;
      case 6:
        $days[] = 'Saturday';
        break;
      }
    } else {
      if($this->getRecurringDays() & 1) {
        $days[] = 'Monday';
      }
      if($this->getRecurringDays() & 2) {
        $days[] = 'Tuesday';
      }
      if($this->getRecurringDays() & 4) {
        $days[] = 'Wednesday';
      }
      if($this->getRecurringDays() & 8) {
        $days[] = 'Thursday';
      }
      if($this->getRecurringDays() & 16) {
        $days[] = 'Friday';
      }
      if($this->getRecurringDays() & 32) {
        $days[] = 'Saturday';
      }
      if($this->getRecurringDays() & 64) {
        $days[] = 'Sunday';
      }
    }
    return $days;
  }

  /**
   * @param DateTime $recurringStop
   * @return void
   */
  public function setRecurringStop($recurringStop) {
    $this->recurringStop = $recurringStop;
  }

  /**
   * @return DateTime
   */
  public function getRecurringStop() {
    return $this->recurringStop;
  }

  /**
   * Set the event stop date
   *
   * @param DateTime $eventStopDate
   * @return void
   */
  public function setEventStopDate($eventStopDate)
  {
    $this->eventStopDate = $eventStopDate;
  }

  /**
   * Get the event stop date
   *
   * @return DateTime
   */
  public function getEventStopDate()
  {
    return ($this->eventStopDate == '') ? $this->eventDate : $this->eventStopDate;
  }
}