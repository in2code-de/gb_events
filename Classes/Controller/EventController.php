<?php

namespace In2code\GbEvents\Controller;

use In2code\GbEvents\Domain\Model\Event;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Http\ForwardResponse;

class EventController extends BaseController
{
    public function listAction(array $filter = []): ResponseInterface
    {
        switch ($this->settings['displayMode'] ?? '') {
            case 'calendar':
                return (new ForwardResponse('show'))->withControllerName('Calendar');
            case 'archive':
                return (new ForwardResponse('list'))->withControllerName('Archive');
            default:
                $events = $this->eventRepository->findAll(
                    $this->settings['years'] ?? 1,
                    (bool)($this->settings['showStartedEvents'] ?? true),
                    $this->settings['categories'] ?? '',
                    $filter,
                    $this->settings['timerange']['start'] ?? '',
                    $this->settings['timerange']['end'] ?? ''
                );
                $this->addCacheTags($events, 'tx_gbevents_domain_model_event');
                $this->view->assign('events', $events);
        }
        return $this->htmlResponse();
    }

    public function showAction(Event $event): ResponseInterface
    {
        $this->addCacheTags($event, 'tx_gbevents_domain_model_event');
        $this->view->assign('event', $event);
        return $this->htmlResponse();
    }
}
