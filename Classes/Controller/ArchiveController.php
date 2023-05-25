<?php

namespace In2code\GbEvents\Controller;

use Psr\Http\Message\ResponseInterface;

/**
 * ArchiveController
 */
class ArchiveController extends BaseController
{
    /**
     * Displays all Events
     *
     * @return void
     */
    public function listAction(): ResponseInterface
    {
        $events = $this->eventRepository->findBygone(
            $this->settings['limit'] ?? 100,
            $this->settings['categories'] ?? '',
            $this->settings['timerange']['start'] ?? '',
            $this->settings['timerange']['end'] ?? ''
        );
        $this->addCacheTags($events, 'tx_gbevents_domain_model_event');
        $this->view->assign('events', $events);
        return $this->htmlResponse();
    }
}
