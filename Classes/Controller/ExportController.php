<?php

namespace In2code\GbEvents\Controller;

use Exception;
use In2code\GbEvents\Domain\Model\Event;
use Psr\Http\Message\ResponseInterface;

/**
 * ExportController
 */
class ExportController extends BaseController
{
    /**
     * Prefix for iCalendar files
     */
    const VCALENDAR_START = "BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:gb_events TYPO3 Extension\nMETHOD:PUBLISH";

    /**
     * Postfix for iCalendar files
     */
    const VCALENDAR_END = 'END:VCALENDAR';

    /**
     * Displays all Events
     *
     * @return string The rendered view
     */
    public function listAction(): ResponseInterface
    {
        $events = $this->eventRepository->findAll(
            $this->settings['years'] ?? 1,
            (bool)($this->settings['showStartedEvents'] ?? true),
            $this->settings['categories'] ?? ''
        );
        $content = [];
        foreach ($events as $event) {
            /** @var Event $event */
            $content[$event->getUniqueIdentifier()] = $event->iCalendarData();
        }
        $this->addCacheTags($events, 'tx_gbevents_domain_model_event');
        $this->renderCalendar(implode(PHP_EOL, $content));
        return $this->htmlResponse();
    }

    /**
     * Exports a single Event as iCalendar file
     *
     * @param Event $event
     * @throws Exception
     */
    public function showAction(Event $event): ResponseInterface
    {
        $this->addCacheTags($event);
        $this->renderCalendar($event->iCalendarData(), $event->iCalendarFilename());
        return $this->htmlResponse();
    }

    /**
     * Set content headers for the iCalendar data
     *
     * @param string $content
     * @param string $filename
     * @throws Exception
     * @return void
     */
    protected function setHeaders($content, $filename)
    {
        if (ob_get_contents()) {
            throw new Exception('Some data has already been sent to the browser', 1408607681);
        }
        header('Content-Type: text/calendar');
        if (headers_sent()) {
            throw new Exception('Some data has already been sent to the browser', 1408607681);
        }

        header('Cache-Control: public');
        header('Pragma: public');
        header('X-Robots-Tag: noindex, nofollow');
        header('Content-Description: iCalendar Event File');
        header('Content-Transfer-Encoding: binary');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        if (!isset($_SERVER['HTTP_ACCEPT_ENCODING']) or empty($_SERVER['HTTP_ACCEPT_ENCODING'])) {
            header('Content-Length: ' . strlen($content));
        }
    }

    /**
     * Render the iCalendar events with the required wrap
     *
     * @param  string $events
     * @param  string $filename
     * @throws Exception
     */
    protected function renderCalendar($events, $filename = 'calendar.ics')
    {
        if (trim($events) === '') {
            throw new Exception('No events to process', 1408611856);
        }
        $content = implode("\n", [
            ExportController::VCALENDAR_START,
            $events,
            ExportController::VCALENDAR_END,
        ]);
        $this->setHeaders($content, $filename);

        echo $content;
        die;
    }
}
