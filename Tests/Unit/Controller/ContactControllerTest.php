<?php
namespace CPSIT\T3eventsReservation\Tests\Unit\Controller;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use CPSIT\T3eventsReservation\Controller\AccessControlInterface;
use CPSIT\T3eventsReservation\Controller\ContactController;
use CPSIT\T3eventsReservation\Domain\Model\Contact;
use CPSIT\T3eventsReservation\Domain\Model\Reservation;
use CPSIT\T3eventsReservation\Domain\Repository\ContactRepository;
use Nimut\TestingFramework\TestCase\UnitTestCase;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Mvc\Web\Request;

class ContactControllerTest extends UnitTestCase
{
    /**
     * @var ContactController
     */
    protected $subject;

    /**
     * @var Request|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $request;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ContactRepository
     */
    protected $repository;

    /**
     * @var ViewInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $view;

    /**
     * @var array
     */
    protected $settings = [];

    public function setUp()
    {
        $this->subject = $this->getAccessibleMock(
            ContactController::class, ['dispatch']
        );
        $this->mockRequest();
        $this->mockView();
        $this->mockContactRepository();
        $this->inject($this->subject, 'settings', $this->settings);
    }

    /**
     * Creates a mock View, injects it and returns it
     *
     * @return ViewInterface | \PHPUnit_Framework_MockObject_MockObject
     */
    protected function mockView()
    {
        $this->view = $this->getMock(ViewInterface::class);
        $this->inject($this->subject, 'view', $this->view);

        return $this->view;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function mockRequest()
    {
        $this->request = $this->getMock(
            Request::class, ['getOriginalRequest', 'hasArgument', 'getArgument']
        );
        $this->inject(
            $this->subject,
            'request',
            $this->request
        );

        return $this->request;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|ContactRepository
     */
    protected function mockContactRepository()
    {
        $this->repository = $this->getMock(
            ContactRepository::class, ['add', 'remove', 'update'], [], '', false
        );

        $this->subject->injectContactRepository($this->repository);
        return $this->repository;
    }

    /**
     * @test
     */
    public function contactRepositoryCanBeInjected()
    {
        $mockRepository = $this->getMock(
            ContactRepository::class, [], [], '', false
        );
        $this->subject->injectContactRepository($mockRepository);
        $this->assertAttributeSame(
            $mockRepository,
            'contactRepository',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function subjectImplementsAccessControlInterface()
    {
        $this->assertInstanceOf(
            AccessControlInterface::class,
            $this->subject
        );
    }

    /**
     * @test
     * @expectedException \TYPO3\CMS\Extbase\Property\Exception\InvalidSourceException
     * @expectedExceptionCode 1460039887
     */
    public function editActionThrowsExceptionIfReservationDoesNotContainContact()
    {
        $contact = new Contact();
        $reservation = new Reservation();

        $this->subject->editAction($contact, $reservation);
    }

    /**
     * @test
     */
    public function editActionAssignsVariablesToView()
    {
        $contact = new Contact();
        $reservation = new Reservation();
        $reservation->setContact($contact);
        $view = $this->mockView();
        $view->expects($this->once())
            ->method('assign')
            ->with('contact', $contact);

        $this->subject->editAction($contact, $reservation);
    }

    /**
     * @test
     */
    public function updateActionCallsDispatch()
    {
        $this->mockContactRepository();
        $contact = new Contact();
        /** @var Reservation $mockReservation */
        $mockReservation = $this->getMock(
            Reservation::class
        );
        $contact->setReservation($mockReservation);

        $this->subject->expects($this->once())
            ->method('dispatch')
            ->with(['reservation' => $mockReservation]);

        $this->subject->updateAction($contact);
    }

    /**
     * @test
     */
    public function newActionAssignsVariablesToView()
    {
        $mockContact = $this->getMock(Contact::class);
        $mockReservation = $this->getMock(Reservation::class);

        $expectedVariables = [
            'contact' => $mockContact,
            'reservation' => $mockReservation
        ];
        $view = $this->mockView();
        $view->expects($this->once())
            ->method('assignMultiple')
            ->with($expectedVariables);

        $this->subject->newAction($mockContact, $mockReservation);
    }

    /**
     * @test
     */
    public function newActionRestoresContactFromRequest()
    {
        $contactFromRequest = $this->getMock(Contact::class);
        $mockReservation = $this->getMock(Reservation::class);

        $this->request->expects($this->once())
            ->method('getOriginalRequest')
            ->will($this->returnValue($this->request));
        $this->request->expects($this->once())
            ->method('hasArgument')
            ->with('contact')
            ->will($this->returnValue(true));
        $this->request->expects($this->once())
            ->method('getArgument')
            ->with('contact')
            ->will($this->returnValue($contactFromRequest));

        $this->subject->newAction(null, $mockReservation);
    }

    /**
     * @test
     */
    public function createActionAddsContactToRepository()
    {
        $mockContact = $this->getMock(Contact::class);
        $mockRepository = $this->mockContactRepository();
        $mockRepository->expects($this->once())
            ->method('add')
            ->with($mockContact);
        $this->subject->createAction($mockContact);
    }

    /**
     * @test
     */
    public function createActionSetsReservationContact()
    {
        $mockContact = $this->getMock(
            Contact::class, ['getReservation']
        );
        $mockReservation = $this->getMock(
            Reservation::class, ['setContact']
        );
        $this->mockContactRepository();

        $mockContact->expects($this->once())
            ->method('getReservation')
            ->will($this->returnValue($mockReservation));
        $mockReservation->expects($this->once())
            ->method('setContact')
            ->with($mockContact);
        $this->subject->createAction($mockContact);
    }

    /**
     * @test
     */
    public function createActionCallsDispatch()
    {
        $this->mockContactRepository();
        $contact = new Contact();
        /** @var Reservation $mockReservation */
        $mockReservation = $this->getMock(
            Reservation::class
        );
        $contact->setReservation($mockReservation);

        $this->subject->expects($this->once())
            ->method('dispatch')
            ->with(['reservation' => $mockReservation]);

        $this->subject->createAction($contact);
    }
}
