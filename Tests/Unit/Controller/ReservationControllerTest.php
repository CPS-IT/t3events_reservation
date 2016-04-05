<?php
namespace CPSIT\T3eventsReservations\Tests\Unit\Controller;
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Dirk Wenzel <wenzel@cps-it.de>, CPS IT
 *  			Boerge Franck <franck@cps-it.de>, CPS IT
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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
use CPSIT\T3eventsReservation\Controller\ReservationController;
use CPSIT\T3eventsReservation\Domain\Model\BillingAddress;
use CPSIT\T3eventsReservation\Domain\Model\BookableInterface;
use CPSIT\T3eventsReservation\Domain\Model\Reservation;
use CPSIT\T3eventsReservation\Domain\Repository\BillingAddressRepository;
use CPSIT\T3eventsReservation\Domain\Repository\PersonRepository;
use CPSIT\T3eventsReservation\Domain\Repository\ReservationRepository;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Tests\UnitTestCase;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use CPSIT\T3eventsReservation\Domain\Model\Notification;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Property\Exception\InvalidSourceException;
use Webfox\T3events\Domain\Model\Performance;
use CPSIT\T3eventsReservation\Domain\Model\Person;
use Webfox\T3events\Domain\Repository\PerformanceRepository;
use Webfox\T3events\Service\NotificationService;
use Webfox\T3events\Session\SessionInterface;
use Webfox\T3events\Session\Typo3Session;
use Webfox\T3events\Utility\SettingsUtility;

/**
 * Test case for class CPSIT\T3eventsReservations\Controller\ReservationController.
 *
 * @author Dirk Wenzel <wenzel@cps-it.de>
 */
class ReservationControllerTest extends UnitTestCase {

	/**
	 * @var ReservationController
	 */
	protected $subject = NULL;

	/**
	 * Creates a mock PersistenceManager, injects it to
	 * subject and returns the mock
	 *
	 * @return mixed
	 */
	protected function mockPersistenceManager() {
		$mockPersistenceManager = $this->getMock(
			PersistenceManager::class
		);
		$this->inject($this->subject, 'persistenceManager', $mockPersistenceManager);

		return $mockPersistenceManager;
	}

	/**
	 * Creates a mock View, injects it and returns it
	 *
	 * @return mixed
	 */
	protected function mockView() {
		$view = $this->getMock(ViewInterface::class);
		$this->inject($this->subject, 'view', $view);

		return $view;
	}

	/**
	 * @return mixed
	 */
	protected function mockReservationRepository() {
		$reservationRepository = $this->getMock(
			ReservationRepository::class, ['add', 'update', 'remove'], [], '', false);
		$this->inject($this->subject, 'reservationRepository', $reservationRepository);

		return $reservationRepository;
	}

    /**
     * @return mixed
     */
    protected function mockPersonRepository() {
        $personRepository = $this->getMock(
            PersonRepository::class, ['add', 'update', 'remove'], [], '', false);
        $this->inject($this->subject, 'personRepository', $personRepository);

        return $personRepository;
    }

	/**
	 * @return mixed
	 */
	protected function mockBillingAddressRepository() {
		$billingAddressRepository = $this->getMock(
			BillingAddressRepository::class, ['add', 'update', 'remove'], [], '', false);
		$this->inject($this->subject, 'billingAddressRepository', $billingAddressRepository);

		return $billingAddressRepository;
	}

	protected function mockAllowAccessReturnsTrue() {
		$this->subject->expects($this->once())
			->method('isAccessAllowed')
			->will($this->returnValue(TRUE));
	}

	/**
	 * @return mixed
	 */
	protected function mockRequest() {
		$mockRequest = $this->getMock(
			Request::class, ['getOriginalRequest', 'getArgument']
		);
		$this->inject($this->subject, 'request', $mockRequest);

		return $mockRequest;
	}

	/**
	 * @return mixed
	 */
	protected function mockSession() {
		$mockSession = $this->getMock(
			SessionInterface::class
		);
		$this->inject($this->subject, 'session', $mockSession);

		return $mockSession;
	}

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
	protected function mockSettingsUtility() {
		$mockSettingsUtility = $this->getMock(
			SettingsUtility::class, ['getValueByKey', 'getFileStorage']
		);
		$this->subject->injectSettingsUtility($mockSettingsUtility);

		return $mockSettingsUtility;
	}

	/**
	 * @return mixed
	 */
	protected function mockLessonRepository() {
		$mockLessonRepository = $this->getMock(
			PerformanceRepository::class, ['add', 'update', 'remove'], [], '', false
		);
		$this->inject($this->subject, 'lessonRepository', $mockLessonRepository);

		return $mockLessonRepository;
	}

	protected function assertDenyAccess() {
		$settings = [
			'schedule' => [
				'listPid' => '3'
			]
		];
		$this->subject->_set('settings', $settings);

		$this->subject->expects($this->once())
			->method('clearCacheOnError');
		$this->subject->_set('actionMethodName', 'fooMethodAction');
		$expectedErrorMessage = 'error.reservation.fooMethod.accessDenied';
		$translatedMessage = 'foo';
		$this->subject->expects($this->once())
			->method('translate')
			->with($expectedErrorMessage)
			->will($this->returnValue($translatedMessage));

		$this->subject->expects($this->once())
			->method('addFlashMessage')
			->with(
				$translatedMessage,
				'',
				AbstractMessage::ERROR,
				true
			);
		$this->subject->expects($this->once())
			->method('redirect')
			->with(
				'list',
				'Performance',
				't3events',
				[],
				$settings['schedule']['listPid']
			);
	}

	/**
	 * @return mixed
	 */
	protected function mockObjectManager() {
		$mockObjectManager = $this->getMock(
			ObjectManager::class, ['get']
		);
		$this->subject->_set('objectManager', $mockObjectManager);

		return $mockObjectManager;
	}

	/**
	 * @return mixed
	 */
	protected function mockNotificationService() {
		$mockNotificationService = $this->getMock(
			NotificationService::class, ['render', 'send']
		);
		$this->inject($this->subject, 'notificationService', $mockNotificationService);

		return $mockNotificationService;
	}

	protected function setUp() {
		$this->subject = $this->getAccessibleMock(
			ReservationController::class,
			['redirect', 'forward', 'addFlashMessage', 'translate', 'isAccessAllowed', 'clearCacheOnError'],
			[], '', false);
		$mockSession = $this->getMock(
			SessionInterface::class, ['get', 'set', 'has', 'clean'], [], '', false
		);
		$this->inject($this->subject, 'session', $mockSession);
	}

	/**
	 * @test
	 */
	public function showActionDeniesAccess() {
		$reservation = new Reservation();
		$this->subject->expects($this->once())
			->method('isAccessAllowed')
			->will($this->returnValue(false));
		$this->assertDenyAccess();
		$this->subject->showAction($reservation);
	}

	/**
	 * @test
	 */
	public function editActionDeniesAccess() {
		$reservation = new Reservation();
		$this->subject->expects($this->once())
			->method('isAccessAllowed')
			->will($this->returnValue(false));
		$this->assertDenyAccess();
		$this->subject->editAction($reservation);
	}

	/**
	 * @test
	 */
	public function deleteActionDeniesAccess() {
		$reservation = new Reservation();
		$this->subject->expects($this->once())
			->method('isAccessAllowed')
			->will($this->returnValue(false));
		$this->assertDenyAccess();
		$this->subject->deleteAction($reservation);
	}

	/**
	 * @test
	 */
	public function newParticipantActionDeniesAccessIfAccessNotAllowed() {
		$reservation = new Reservation();
		$this->subject->expects($this->once())
			->method('isAccessAllowed')
			->will($this->returnValue(false));
		$this->assertDenyAccess();
		$this->subject->newParticipantAction($reservation);
	}

	/**
	 * @test
	 */
	public function newParticipantActionDoesNotDenyAccessIfReservationStatusIsDraft() {
		$mockRequest = $this->getMock(
			Request::class
		);
		$this->mockView();
		$this->inject($this->subject, 'request', $mockRequest);
		$reservation = new Reservation();
		$reservation->setStatus(Reservation::STATUS_DRAFT);

		$this->mockAllowAccessReturnsTrue();
		$this->subject->expects($this->never())
			->method('denyAccess');
		$this->subject->newParticipantAction($reservation);
	}

	/**
	 * @test
	 */
	public function removeParticipantActionDeniesAccess() {
		$reservation = new Reservation();
		$participant = new Person();
		$this->subject->expects($this->once())
			->method('isAccessAllowed')
			->will($this->returnValue(false));
		$this->assertDenyAccess();
		$this->subject->removeParticipantAction($reservation, $participant);
	}

	/**
	 * @test
	 */
	public function confirmActionDeniesAccess() {
		$reservation = new Reservation();
		$this->subject->expects($this->once())
			->method('isAccessAllowed')
			->will($this->returnValue(false));
		$this->assertDenyAccess();
		$this->subject->confirmAction($reservation);
	}

    /**
     * @test
     */
    public function confirmActionSetsStatusSubmitted() {
        $mockReservation = $this->getMock(
            Reservation::class, ['setStatus']
        );
        $this->mockAllowAccessReturnsTrue();
        $this->mockReservationRepository();

        $mockReservation->expects($this->once())
            ->method('setStatus')
            ->with(Reservation::STATUS_SUBMITTED);

        $this->subject->confirmAction($mockReservation);
    }

    /**
     * @test
     */
    public function confirmActionAddsFlashMessage() {
        $mockReservation = $this->getMock(Reservation::class);
        $this->mockAllowAccessReturnsTrue();
        $this->mockReservationRepository();
        $translatedMessage = 'foo';
        $this->subject->expects($this->once())
            ->method('translate')
            ->with('message.reservation.confirm.success')
            ->will($this->returnValue($translatedMessage)
        );
        $this->subject->expects($this->once())
            ->method('addFlashMessage')
            ->with($translatedMessage);

        $this->subject->confirmAction($mockReservation);
    }

    /**
     * @test
     */
    public function confirmActionSendsNotification() {
        $this->subject = $this->getAccessibleMock(
            ReservationController::class,
            ['sendNotification', 'redirect', 'forward', 'addFlashMessage', 'translate', 'isAccessAllowed', 'clearCacheOnError'],
            [], '', false);
        $identifier = 'foo';
        $configForIdentifier = ['bar'];

        $settings = [
            'reservation' => [
                'confirm' => [
                    'notification' => [
                        $identifier => $configForIdentifier
                    ]
                ]
            ]
        ];
        $this->subject->_set('settings', $settings);

        $mockReservation = $this->getMock(Reservation::class);
        $this->mockAllowAccessReturnsTrue();
        $this->mockReservationRepository();

        $this->subject->expects($this->once())
            ->method('sendNotification')
            ->with(
                $mockReservation,
                $identifier,
                $configForIdentifier
            );

        $this->subject->confirmAction($mockReservation);
    }

    /**
     * @test
     */
    public function confirmActionUpdatesReservation() {
        $mockReservation = $this->getMock(Reservation::class);
        $this->mockAllowAccessReturnsTrue();
        $mockRepository = $this->mockReservationRepository();
        $mockRepository->expects($this->once())
            ->method('update')
            ->with($mockReservation);

        $this->subject->confirmAction($mockReservation);
    }

    /**
     * @test
     */
    public function confirmActionForwardToShowAction() {
        $mockReservation = $this->getMock(Reservation::class);
        $this->mockAllowAccessReturnsTrue();
        $this->mockReservationRepository();
        $this->subject->expects($this->once())
            ->method('forward')
            ->with(
                'show',
                null,
                null,
                ['reservation' => $mockReservation]
            );

        $this->subject->confirmAction($mockReservation);
    }

    /**
	 * @test
	 */
	public function checkoutActionDeniesAccess() {
		$reservation = new Reservation();
		$this->subject->expects($this->once())
			->method('isAccessAllowed')
			->will($this->returnValue(false));
		$this->assertDenyAccess();
		$this->subject->checkoutAction($reservation);
	}

    /**
     * @test
     */
    public function checkoutActionAssignsReservationToView() {
        $this->mockAllowAccessReturnsTrue();
        $reservation = new Reservation();
        $view = $this->mockView();
        $view->expects($this->once())
            ->method('assign')
            ->with('reservation', $reservation);

        $this->subject->checkoutAction($reservation);
    }

    /**
	 * @test
	 */
	public function createParticipantActionDeniesAccess() {
		$reservation = new Reservation();
		$participant = new Person();
		$this->subject->expects($this->once())
			->method('isAccessAllowed')
			->will($this->returnValue(false));
		$this->assertDenyAccess();
		$this->subject->createParticipantAction($reservation, $participant);
	}

	/**
	 * @test
	 */
	public function createActionDeniesAccessIfReservationIsNotNew() {
		$reservation = $this->getMock(
			Reservation::class, ['getUid']
		);
		$reservation->expects($this->once())
			->method('getUid')
			->will($this->returnValue(5));
		$this->assertDenyAccess();
		$this->subject->createAction($reservation);
	}

	/**
	 * @test
	 */
	public function createActionDeniesAccessReservationIsInSession() {
		$reservation = $this->getMock(
			Reservation::class
		);
		$mockSession = $this->mockSession();
		$mockSession->expects($this->once())
			->method('has')
			->with('reservationUid')
			->will($this->returnValue(true));
		$this->assertDenyAccess();

		$this->subject->createAction($reservation);
	}

	/**
	 * @test
	 */
	public function showActionAssignsReservationToView() {
		$this->mockAllowAccessReturnsTrue();
		$reservation = new Reservation();
		$view = $this->mockView();
		$view->expects($this->once())->method('assign')->with('reservation', $reservation);

		$this->subject->showAction($reservation);
	}

    /**
     * @test
     */
    public function showActionCleansSession() {
        $this->mockAllowAccessReturnsTrue();
        $reservation = new Reservation();
        $this->mockView();
        $session = $this->mockSession();
        $session->expects($this->once())
            ->method('clean');

        $this->subject->showAction($reservation);
    }

	/**
	 * @test
	 */
	public function newActionAssignsVariablesToView() {
		$mockRequest = $this->mockRequest();
		$mockRequest->expects($this->once())
			->method('getOriginalRequest');
		$reservation = new Reservation();
		$mockLesson = $this->getMock(
			Performance::class, ['getFreePlaces']
		);
		$mockLesson->expects($this->once())
			->method('getFreePlaces')
			->will($this->returnValue(99));

		$view = $this->getMock(ViewInterface::class);
		$view->expects($this->once())
			->method('assignMultiple')
			->with(
				[
					'newReservation' => $reservation,
					'lesson' => $mockLesson
				]
			);
		$this->inject($this->subject, 'view', $view);

		$this->subject->newAction($mockLesson, $reservation);
	}

	/**
	 * @test
	 */
	public function createActionAddsReservationToReservationRepository() {
		$this->mockPersistenceManager();

		$mockReservation = $this->getMock(
			Reservation::class, ['getContact']
		);
		$mockContact = $this->getMock(
			Person::class
		);
		$mockReservation->expects($this->once())
			->method('getContact')
			->will($this->returnValue($mockContact));

		$reservationRepository = $this->mockReservationRepository();
		$reservationRepository->expects($this->once())
			->method('add')
			->with($mockReservation);

		$this->subject->createAction($mockReservation);
	}

	/**
	 * @test
	 */
	public function editActionAssignsReservationToView() {
		$this->mockAllowAccessReturnsTrue();
		$this->mockPersistenceManager();
		$reservationRepository = $this->mockReservationRepository();
		$view = $this->mockView();

		$reservation = new Reservation();

		$view->expects($this->once())
			->method('assignMultiple')
			->with(
				[
				'reservation' => $reservation
				]
			);

		$reservationRepository->expects($this->once())
			->method('update')
			->with($reservation);

		$this->subject->editAction($reservation);
	}

	/**
	 * @test
	 */
	public function createParticipantUpdatesReservationInReservationRepository() {
		$this->mockAllowAccessReturnsTrue();
		$this->mockPersistenceManager();
		$this->mockLessonRepository();

		$reservation = $this->getMock(
			Reservation::class, ['getLesson']
		);
		$newParticipant = new Person();
		$mockLesson = $this->getMock(
			Performance::class, ['getFreePlaces', 'addParticipant']
		);
		$reservation->expects($this->any())
			->method('getLesson')
			->will($this->returnValue($mockLesson));
		$mockLesson->expects($this->once())
			->method('getFreePlaces')
			->will($this->returnValue(99));
		$reservationRepository = $this->mockReservationRepository();
		$reservationRepository->expects($this->once())
			->method('update')
			->with($reservation);

		$this->subject->createParticipantAction($reservation, $newParticipant);
	}

	/**
	 * @test
	 */
	public function deleteActionRemovesReservationFromReservationRepository() {
		$this->mockAllowAccessReturnsTrue();

		$reservation = new Reservation();

		$reservationRepository = $this->mockReservationRepository();
		$reservationRepository->expects($this->once())
			->method('remove')
			->with($reservation);

		$this->subject->deleteAction($reservation);
	}

	/**
	 * @test
	 */
	public function initializeActionSetsSession() {
		$this->subject = $this->getAccessibleMock(
			ReservationController::class,
			['setRequestArguments', 'setReferrerArguments'],
			[], '', false);
		$mockObjectManager = $this->mockObjectManager();
		$mockSession = $this->getMockForAbstractClass(
			SessionInterface::class
		);
		$mockObjectManager->expects($this->once())
			->method('get')
			->with(Typo3Session::class, ReservationController::SESSION_NAME_SPACE)
			->will($this->returnValue($mockSession));
		$this->subject->initializeAction();
		$this->assertAttributeEquals(
			$mockSession,
			'session',
			$this->subject
		);
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Extbase\Configuration\Exception
	 * @expectedExceptionCode 1454518855
	 */
	public function sendNotificationThrowsExceptionIfFromEmailIsNotSet() {
		$config = [];
		$identifier = 'foo';
		$this->mockSettingsUtility();
		$reservation = new Reservation();
		$this->subject->_callRef('sendNotification', $reservation, $identifier, $config);
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Extbase\Configuration\Exception
	 * @expectedExceptionCode 1454865240
	 */
	public function sendNotificationThrowsExceptionIfRecipientEmailIsNotSet() {
		$config = [
			'fromEmail' => 'foo@bar.com'
		];
		$identifier = 'foo';
		$this->mockSettingsUtility();
		$reservation = new Reservation();
		$this->subject->_callRef('sendNotification', $reservation, $identifier, $config);
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Extbase\Configuration\Exception
	 * @expectedExceptionCode 1454865250
	 */
	public function sendNotificationThrowsExceptionIfSubjectIsNotSet() {
		$config = [
			'fromEmail' => 'foo@bar.com',
			'toEmail' => 'bar@baz.com'
		];
		$identifier = 'foo';
		$this->subject->injectSettingsUtility(new SettingsUtility());
		$reservation = new Reservation();
		$this->subject->_callRef('sendNotification', $reservation, $identifier, $config);
	}

	/**
	 * @test
	 */
	public function sendNotificationSendsNotification() {
		$settings = ['foo'];
		$this->subject->_set('settings', $settings);
		$config = [
			'fromEmail' => 'foo@bar.com',
			'toEmail' => 'bar@baz.com',
			'subject' => 'baz'
		];
		$this->subject->injectSettingsUtility(new SettingsUtility());

		$identifier = 'foo';
		$reservation = new Reservation();
		$mockNotification = $this->getMock(
			Notification::class,
			['setRecipient', 'setSender', 'setSubject', 'setFormat', 'setBodyText']
		);
		$mockObjectManager = $this->mockObjectManager();
		$mockObjectManager->expects($this->once())
			->method('get')
			->with(Notification::class)
			->will($this->returnValue($mockNotification));
		$mockNotificationService = $this->mockNotificationService();
		$mockNotificationService->expects($this->once())
			->method('render')
			->with(
				ucfirst($identifier),
				'plain',
				'Reservation/Email',
				['reservation' => $reservation, 'settings' => $settings]
			);
		$this->subject->_callRef('sendNotification', $reservation, $identifier, $config);
	}


	/**
	 * @test
	 */
	public function sendNotificationGetsToEmailByPropertyPath() {
		$settings = ['foo'];
		$this->subject->_set('settings', $settings);
		$config = [
			'fromEmail' => 'foo@bar.com',
			'toEmail' => [
				'field' => 'contact.email'
			],
			'subject' => 'baz'
		];
		$this->subject->injectSettingsUtility(new SettingsUtility());
		$identifier = 'foo';
		$email = 'bar@baz.com';
		$mockContact = $this->getAccessibleMock(
			Person::class, ['getEmail']
		);
		$reservation = $this->getAccessibleMock(
			Reservation::class, ['getContact']
		);
		$reservation->expects($this->once())
			->method('getContact')
			->will($this->returnValue($mockContact));
		$mockContact->expects($this->once())
			->method('getEmail')
			->will($this->returnValue($email));
		$mockNotification = $this->getMock(
			Notification::class,
			['setRecipient', 'setSender', 'setSubject', 'setFormat', 'setBodyText']
		);
		$mockNotification->expects($this->once())
			->method('setRecipient')
			->with($email);
		$mockObjectManager = $this->mockObjectManager();
		$mockObjectManager->expects($this->once())
			->method('get')
			->with(Notification::class)
			->will($this->returnValue($mockNotification));
		$mockNotificationService = $this->mockNotificationService();
		$mockNotificationService->expects($this->once())
			->method('render')
			->with(
				ucfirst($identifier),
				'plain',
				'Reservation/Email',
				['reservation' => $reservation, 'settings' => $settings]
			);
		$this->subject->_callRef('sendNotification', $reservation, $identifier, $config);
	}

	/**
	 * @test
	 */
	public function sendNotificationGetsFormatFromSettings() {
		$settings = ['foo'];
		$this->subject->_set('settings', $settings);
		$config = [
			'fromEmail' => 'foo@bar.com',
			'toEmail' => 'bar@baz.com',
			'subject' => 'baz',
			'format' => 'html'
		];
		$identifier = 'foo';
		$this->subject->injectSettingsUtility(new SettingsUtility());
		$reservation = new Reservation();
		$mockNotification = $this->getMock(
			Notification::class,
			['setRecipient', 'setSender', 'setSubject', 'setFormat', 'setBodyText']
		);
		$mockObjectManager = $this->mockObjectManager();
		$mockObjectManager->expects($this->once())
			->method('get')
			->with(Notification::class)
			->will($this->returnValue($mockNotification));
		$mockNotificationService = $this->mockNotificationService();
		$mockNotificationService->expects($this->once())
			->method('render')
			->with(
				ucfirst($identifier),
				'html',
				'Reservation/Email',
				['reservation' => $reservation, 'settings' => $settings]
			);
		$this->subject->_callRef('sendNotification', $reservation, $identifier, $config);
	}

	/**
	 * @test
	 */
	public function sendNotificationGetsTemplateFileNameFromSettings() {
		$settings = ['foo'];
		$this->subject->_set('settings', $settings);
		$config = [
			'fromEmail' => 'foo@bar.com',
			'toEmail' => 'bar@baz.com',
			'subject' => 'baz',
			'format' => 'html',
			'template' => [
				'fileName' => 'fooFileName'
			]
		];
		$identifier = 'foo';
		$this->subject->injectSettingsUtility(new SettingsUtility());
		$reservation = new Reservation();
		$mockNotification = $this->getMock(
			Notification::class,
			['setRecipient', 'setSender', 'setSubject', 'setFormat', 'setBodyText']
		);
		$mockObjectManager = $this->mockObjectManager();
		$mockObjectManager->expects($this->once())
			->method('get')
			->with(Notification::class)
			->will($this->returnValue($mockNotification));
		$mockNotificationService = $this->mockNotificationService();
		$mockNotificationService->expects($this->once())
			->method('render')
			->with(
				'fooFileName',
				'html',
				'Reservation/Email',
				['reservation' => $reservation, 'settings' => $settings]
			);
		$this->subject->_callRef('sendNotification', $reservation, $identifier, $config);
	}

	/**
	 * @test
	 */
	public function sendNotificationAddsAttachments() {
		$reservation = new Reservation();
		$identifier = 'foo';
		$fileUtilityConfig = [
			'foo' => 'bar'
		];
		$config = [
			'attachments' => [
				'files' => $fileUtilityConfig
			],
			'fromEmail' => 'foo@bar.com',
			'toEmail' => 'bar@baz.com',
			'subject' => 'baz',
		];
		$mockObjectStorage = $this->getMock(
			ObjectStorage::class
		);
		$mockSettingsUtility = $this->mockSettingsUtility();
		$mockSettingsUtility->expects($this->once())
			->method('getFileStorage')
			->with($reservation, $fileUtilityConfig)
			->will($this->returnValue($mockObjectStorage));
		$mockSettingsUtility->expects($this->any())
			->method('getValueByKey')
			->will($this->returnValue('barBaz'));
		$mockNotification = $this->getMock(
			Notification::class,
			['setAttachments']
		);
		$mockObjectManager = $this->mockObjectManager();
		$mockObjectManager->expects($this->once())
			->method('get')
			->with(Notification::class)
			->will($this->returnValue($mockNotification));
		$this->mockNotificationService();
		$mockNotification->expects($this->once())
			->method('setAttachments')
			->with($mockObjectStorage);

		$this->subject->_callRef('sendNotification', $reservation, $identifier, $config);
	}

	/**
	 * @test
	 */
	public function sendNotificationGetsTemplateFolderFromSettings() {
		$settings = ['foo'];
		$this->subject->_set('settings', $settings);
		$this->subject->injectSettingsUtility(new SettingsUtility());
		$folderName = 'fooFolder';
		$config = [
			'fromEmail' => 'foo@bar.com',
			'toEmail' => 'bar@baz.com',
			'subject' => 'baz',
			'format' => 'html',
			'template' => [
				'folderName' => $folderName
			]
		];
		$identifier = 'foo';
		$reservation = new Reservation();
		$mockNotification = $this->getMock(
			Notification::class,
			['setRecipient', 'setSender', 'setSubject', 'setFormat', 'setBodyText']
		);
		$mockObjectManager = $this->mockObjectManager();
		$mockObjectManager->expects($this->once())
			->method('get')
			->with(Notification::class)
			->will($this->returnValue($mockNotification));
		$mockNotificationService = $this->mockNotificationService();
		$mockNotificationService->expects($this->once())
			->method('render')
			->with(
				ucfirst($identifier),
				'html',
				$folderName,
				['reservation' => $reservation, 'settings' => $settings]
			);
		$this->subject->_callRef('sendNotification', $reservation, $identifier, $config);
	}

	/**
	 * @test
	 */
	public function newParticipantActionAssignsVariablesToView() {
		$mockReservation = $this->getAccessibleMock(
			Reservation::class, ['getStatus', 'getLesson']
		);
		$mockLesson = $this->getMock(
			BookableInterface::class
		);
		$mockLesson->expects($this->once())
			->method('getFreePlaces')
			->will($this->returnValue(1));
		$this->mockAllowAccessReturnsTrue();

		$mockReservation->expects($this->any())
			->method('getStatus')
			->will($this->returnValue(Reservation::STATUS_DRAFT));
		$mockReservation->expects($this->once())
			->method('getLesson')
			->will($this->returnValue($mockLesson));
		$mockRequest = $this->mockRequest();
		$mockView = $this->mockView();
		$mockView->expects($this->once())
			->method('assignMultiple')
			->with(
				[
					'newParticipant' => null,
					'reservation' => $mockReservation
				]
			);
		$this->subject->newParticipantAction($mockReservation);
	}

	/**
	 * @test
	 */
	public function newParticipantActionSetsStatusDraft() {
		$mockReservation = $this->getAccessibleMock(
			Reservation::class, ['getStatus', 'setStatus', 'getLesson']
		);
		$mockLesson = $this->getMock(BookableInterface::class);
		$mockLesson->expects($this->once())
			->method('getFreePlaces')
			->will($this->returnValue(1));
        $this->mockAllowAccessReturnsTrue();

		$mockReservation->expects($this->any())
			->method('getStatus')
			->will($this->returnValue(Reservation::STATUS_NEW));
		$mockReservation->expects($this->any())
			->method('setStatus')
			->with(Reservation::STATUS_DRAFT);
		$mockReservation->expects($this->once())
			->method('getLesson')
			->will($this->returnValue($mockLesson));
		$this->mockRequest();
		$this->mockView();
		$this->subject->newParticipantAction($mockReservation);
	}

	/**
	 * @test
	 */
	public function newParticipantActionAddsFlashMessageIfNoFreePlaces() {
		$mockReservation = $this->getAccessibleMock(
			Reservation::class, ['getStatus', 'setStatus', 'getLesson']
		);
        $this->mockAllowAccessReturnsTrue();

		$mockLesson = $this->getMock(BookableInterface::class);
		$mockLesson->expects($this->once())
			->method('getFreePlaces')
			->will($this->returnValue(0));
		$mockReservation->expects($this->any())
			->method('getStatus')
			->will($this->returnValue(Reservation::STATUS_NEW));
        $mockReservation->expects($this->once())
			->method('getLesson')
			->will($this->returnValue($mockLesson));
		$this->mockRequest();
		$this->mockView();
		$mockMessage = 'fooMessage';
		$this->subject->expects($this->once())
			->method('translate')
			->with('message.noFreePlacesForThisLesson')
			->will($this->returnValue($mockMessage));
		$this->subject->expects($this->once())
			->method('addFlashMessage')
			->with(
				$mockMessage,
				'',
				AbstractMessage::ERROR,
				true
			);
		$this->subject->newParticipantAction($mockReservation);
	}

	/**
	 * @test
	 */
	public function newParticipantGetsParticipantFromOriginalRequest() {
		$mockReservation = $this->getAccessibleMock(
			Reservation::class, ['getStatus', 'setStatus', 'getLesson']
		);
		$this->mockAllowAccessReturnsTrue();

        $mockLesson = $this->getMock(BookableInterface::class);
		$mockLesson->expects($this->once())
			->method('getFreePlaces')
			->will($this->returnValue(3));

		$mockReservation->expects($this->any())
			->method('getStatus')
			->will($this->returnValue(Reservation::STATUS_NEW));
		$mockReservation->expects($this->once())
			->method('getLesson')
			->will($this->returnValue($mockLesson));
		$this->mockView();
		$mockRequest = $this->mockRequest();
		$mockRequest->expects($this->any())
			->method('getOriginalRequest')
			->will($this->returnValue($mockRequest));
		$mockRequest->expects($this->once())
			->method('getArgument')
			->with('newParticipant');
		$this->subject->newParticipantAction($mockReservation);
	}

	/**
	 * @test
	 */
	public function editBillingAddressActionDeniesAccess() {
		$reservation = new Reservation();
		$this->subject->expects($this->once())
			->method('isAccessAllowed')
			->will($this->returnValue(false));
		$this->assertDenyAccess();
		$this->subject->editBillingAddressAction($reservation);
	}

	/**
	 * @test
	 */
	public function editBillingAddressActionAssignsReservationToView() {
		$this->mockAllowAccessReturnsTrue();
		$view = $this->mockView();

		$reservation = new Reservation();

		$view->expects($this->once())
			->method('assignMultiple')
			->with(
				[
					'reservation' => $reservation
				]
			);

		$this->subject->editBillingAddressAction($reservation);
	}

	/**
	 * @test
	 */
	public function updateActionDeniesAccess() {
		$reservation = new Reservation();
		$this->subject->expects($this->once())
			->method('isAccessAllowed')
			->will($this->returnValue(false));
		$this->assertDenyAccess();

		$this->subject->updateAction($reservation);
	}

	/**
	 * @test
	 */
	public function updateActionUpdatesReservation() {
		$this->mockAllowAccessReturnsTrue();
		$reservationRepository = $this->mockReservationRepository();

		$reservation = new Reservation();

		$reservationRepository->expects($this->once())
			->method('update')
			->with($reservation);

		$this->subject->updateAction($reservation);
	}

	/**
	 * @test
	 */
	public function updateActionAddsFlashMessageOnSuccess() {
		$this->mockAllowAccessReturnsTrue();
		$this->mockReservationRepository();
		$translatedMessage = 'foo';
		$reservation = new Reservation();

		$this->subject->expects($this->once())
			->method('translate')
			->with('message.reservation.update.success')
			->will($this->returnValue($translatedMessage));
		$this->subject->expects($this->once())
			->method('addFlashMessage')
			->with($translatedMessage);

		$this->subject->updateAction($reservation);
	}

	/**
	 * @test
	 */
	public function updateActionRedirectsToEditAction() {
		$this->mockAllowAccessReturnsTrue();
		$this->mockReservationRepository();
		$reservation = new Reservation();

		$this->subject->expects($this->once())
			->method('redirect')
			->with(
				'edit',
				null,
				null,
				[
					'reservation' => $reservation
				]
			);

		$this->subject->updateAction($reservation);
	}

    /**
     * @test
     */
    public function removeBillingAddressActionDeniesAccess() {
        $reservation = new Reservation();
        $billingAddress = new BillingAddress();
        $this->subject->expects($this->once())
            ->method('isAccessAllowed')
            ->will($this->returnValue(false));
        $this->assertDenyAccess();
        $this->subject->removeBillingAddressAction($reservation, $billingAddress);
    }

    /**
     * @test
     */
    public function removeBillingAddressAddsMessageOnSuccess()
    {
        $this->mockAllowAccessReturnsTrue();
        $this->mockBillingAddressRepository();

        /** @var Reservation $reservation */
        $reservation = $this->getMock(
            Reservation::class, ['getBillingAddress']
        );
        /** @var BillingAddress $mockBillingAddress */
        $mockBillingAddress = $this->getMock(
            BillingAddress::class
        );
        $reservation->expects($this->any())
            ->method('getBillingAddress')
            ->will($this->returnValue($mockBillingAddress));
        $expectedKey = 'message.reservation.removeBillingAddress.success';
        $this->subject->expects($this->once())
            ->method('translate')
            ->with($expectedKey)
            ->will($this->returnValue($expectedKey));
        $this->subject->expects($this->once())
            ->method('addFlashMessage')
            ->with($expectedKey);

        $this->subject->removeBillingAddressAction($reservation);
    }

    /**
     * @test
     */
    public function removeBillingAddressRedirectsToEditAction()
    {
        $this->mockAllowAccessReturnsTrue();
        /** @var Reservation $mockReservation */
        $mockReservation = $this->getMock(
            Reservation::class, ['getBillingAddress']
        );
        $this->subject->expects($this->once())
            ->method('redirect')
            ->with(
                'edit',
                null,
                null,
                [
                    'reservation' => $mockReservation
                ]
            );

        $this->subject->removeBillingAddressAction($mockReservation);
    }

    /**
     * @test
     */
    public function newBillingAddressActionDeniesAccess() {
        $reservation = new Reservation();
        $this->assertDenyAccess();
        $this->subject->newBillingAddressAction($reservation);
    }

    /**
     * @test
     */
    public function newBillingAddressActionAssignsVariablesToView()
    {
        $this->mockAllowAccessReturnsTrue();
        $mockReservation = $this->getAccessibleMock(
            Reservation::class
        );

        $mockView = $this->mockView();
        $mockView->expects($this->once())
            ->method('assignMultiple')
            ->with(
                [
                    'newBillingAddress' => null,
                    'reservation' => $mockReservation
                ]
            );

        $this->subject->newBillingAddressAction($mockReservation);
    }

    /**
     * @test
     */
    public function createBillingAddressActionDeniesAccess() {
        $reservation = new Reservation();
        $billingAddress = new BillingAddress();
        $this->assertDenyAccess();
        $this->subject->createBillingAddressAction($reservation, $billingAddress);
    }

    /**
     * @test
     */
    public function createBillingAddressActionSetsBillingAddress()
    {
        $this->mockAllowAccessReturnsTrue();
        $this->mockReservationRepository();
        /** @var Reservation $reservation */
        $reservation = $this->getMock(
            Reservation::class, ['setBillingAddress']
        );
        /** @var BillingAddress $billingAddress */
        $billingAddress = $this->getMock(
            BillingAddress::class
        );
        $this->mockPersonRepository();
        $reservation->expects($this->once())
            ->method('setBillingAddress')
            ->with($billingAddress);

        $this->subject->createBillingAddressAction($reservation, $billingAddress);
    }

    /**
     * @test
     */
    public function createBillingAddressActionAddsBillingAddressToRepository()
    {
        $this->mockAllowAccessReturnsTrue();
        /** @var Reservation $reservation */
        $reservation = $this->getMock(
            Reservation::class
        );
        /** @var BillingAddress $billingAddress */
        $billingAddress = $this->getMock(
            BillingAddress::class
        );
        $this->mockReservationRepository();
        $mockPersonRepository = $this->mockPersonRepository();
        $mockPersonRepository->expects($this->once())
            ->method('add')
            ->with($billingAddress);

        $this->subject->createBillingAddressAction($reservation, $billingAddress);
    }

    /**
     * @test
     */
    public function createBillingAddressUpdatesReservation()
    {
        $this->mockAllowAccessReturnsTrue();
        /** @var Reservation $reservation */
        $reservation = $this->getMock(
            Reservation::class
        );
        /** @var BillingAddress $billingAddress */
        $billingAddress = $this->getMock(
            BillingAddress::class
        );
        $this->mockPersonRepository();
        $mockReservationRepository = $this->mockReservationRepository();
        $mockReservationRepository->expects($this->once())
            ->method('update')
            ->with($reservation);

        $this->subject->createBillingAddressAction($reservation, $billingAddress);
    }


    /**
     * @test
     */
    public function createBillingAddressAddsMessageOnSuccess()
    {
        $this->mockAllowAccessReturnsTrue();
        $this->mockPersonRepository();
        $this->mockReservationRepository();

        /** @var Reservation $reservation */
        $reservation = $this->getMock(
            Reservation::class
        );
        /** @var BillingAddress $mockBillingAddress */
        $mockBillingAddress = $this->getMock(
            BillingAddress::class
        );
        $expectedKey = 'message.reservation.createBillingAddress.success';
        $this->subject->expects($this->once())
            ->method('translate')
            ->with($expectedKey)
            ->will($this->returnValue($expectedKey));
        $this->subject->expects($this->once())
            ->method('addFlashMessage')
            ->with($expectedKey);

        $this->subject->createBillingAddressAction($reservation, $mockBillingAddress);
    }

    /**
     * @test
     */
    public function createBillingAddressActionRedirectsToEditAction()
    {
        $this->mockAllowAccessReturnsTrue();
        $this->mockReservationRepository();
        $this->mockPersonRepository();

        /** @var Reservation $mockReservation */
        $mockReservation = $this->getMock(
            Reservation::class
        );
        /** @var BillingAddress $mockBillingAddress */
        $mockBillingAddress = $this->getMock(
            BillingAddress::class
        );
        $this->subject->expects($this->once())
            ->method('redirect')
            ->with(
                'edit',
                null,
                null,
                [
                    'reservation' => $mockReservation
                ]
            );

        $this->subject->createBillingAddressAction($mockReservation, $mockBillingAddress);
    }
}
