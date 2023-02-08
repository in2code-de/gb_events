<?php

namespace In2code\GbEvents\Controller;

use ArrayAccess;
use In2code\GbEvents\Domain\Repository\EventRepository;
use Psr\Http\Message\ResponseInterface;
use Traversable;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Property\Exception as ExceptionExtbaseProperty;
use TYPO3\CMS\Extbase\Property\Exception\TargetNotFoundException;
use TYPO3\CMS\Frontend\Controller\ErrorController;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * ArchiveController
 */
abstract class BaseController extends ActionController
{
    /**
     * @var EventRepository
     */
    protected $eventRepository;

    /**
     * @var DataMapper
     */
    protected $dataMapper;

    /**
     * inject the eventRepository
     *
     * @param \In2code\GbEvents\Domain\Repository\EventRepository eventRepository
     * @return void
     */
    public function injectEventRepository(EventRepository $eventRepository)
    {
        $this->eventRepository = $eventRepository;
    }

    /**
     * @param ViewInterface $view
     * @return void
     */
    public function initializeView(ViewInterface $view)
    {
        parent::initializeView($view);
        $this->view->assignMultiple([
            'data' => $this->configurationManager->getContentObject()->data,
        ]);
    }

    /**
     * Dynamically add the right tags to the page cache for a details or list view
     *
     * @param mixed $items
     * @param string|array $additionalTags
     */
    protected function addCacheTags($items, $additionalTags = null)
    {
        if (ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isBackend()) {
            return;
        }

        if (!is_array($items) && !$items instanceof Traversable && !$items instanceof ArrayAccess) {
            $items = [$items];
        }
        if (!is_array($additionalTags)) {
            $additionalTags = [(string)$additionalTags];
        }

        $tags = $additionalTags;
        foreach ($items as $item) {
            if ($item instanceof AbstractEntity) {
                $table = $this->getDataMapper()->convertClassNameToTableName(get_class($item));
                $uid = $item->getUid();
                $tags[] = sprintf('%s_%s', $table, $uid);
            } elseif (is_string($item) && (string)$item !== '') {
                $tags[] = (string)$item;
            }
        }

        if (!empty($tags)) {
            $this->getTypoScriptFrontEndController()->addCacheTags($tags);
        }
    }

    /**
     * @param RequestInterface $request
     * @return ResponseInterface
     * @throws ExceptionExtbaseProperty
     */
    public function processRequest(RequestInterface $request): ResponseInterface
    {
        try {
            $response = parent::processRequest($request);
        } catch (ExceptionExtbaseProperty $exception) {
            if ($exception instanceof TargetNotFoundException) {
                $response = GeneralUtility::makeInstance(ErrorController::class)->pageNotFoundAction(
                    $GLOBALS['TYPO3_REQUEST'],
                    'Die gesuchte Veranstaltung wurde nicht gefunden.'
                );
            } else {
                throw $exception;
            }
        }
        return $response;
    }

    /**
     * @return DataMapper
     */
    protected function getDataMapper()
    {
        if (!isset($this->dataMapper)) {
            $this->dataMapper = $this->objectManager->get(DataMapper::class);
        }

        return $this->dataMapper;
    }

    /**
     * @return TypoScriptFrontendController
     */
    protected function getTypoScriptFrontEndController()
    {
        return $GLOBALS['TSFE'];
    }
}
