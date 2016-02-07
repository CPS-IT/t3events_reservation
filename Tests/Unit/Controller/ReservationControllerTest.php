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
use CPSIT\T3eventsReservation\Domain\Model\Reservation;
use CPSIT\T3eventsReservation\Domain\Repository\ReservationRepository;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Tests\UnitTestCase;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use Webfox\T3events\Domain\Model\Performance;
use \CPSIT\T3eventsReservation\Domain\Model\Person;
use Webfox\T3events\Domain\Repository\PerformanceRepository;
use Webfox\T3events\Session\SessionInterface;
use Webfox\T3events\Session\Typo3Session;

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
			ReservationRepository::class, ['add', 'update', 'remove'], [], '', FALSE);
		$this->inject($this->subject, 'reservationRepository', $reservationRepository);

		return $reservationRepository;
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
	public function isAccessAllowedReturnsFalseIfObjectIsNotReservation() {
		$this->subject = $this->getAccessibleMock(
			ReservationController::class, ['dummy']
		);
		$object = $this->getMockForAbstractClass(
			DomainObjectInterface::class
		);
		$this->assertFalse(
			$this->subject->isAccessAllowed($object)
		);
	}

	/**
	 * @test
	 */
	public function isAccessAllowedReturnsFalseIfReservationUidIsNotInSession() {
		$this->subject = $this->getAccessibleMock(
			ReservationController::class, ['dummy']
		);
		$mockSession = $this->mockSession();
		$object = $this->getMock(
			Reservation::class
		);
		$mockSession->expects($this->once())
			->method('has')
			->with('reservationUid')
			->will($this->returnValue(false));

		$this->assertFalse(
			$this->subject->isAccessAllowed($object)
		);
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


}
