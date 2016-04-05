<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
	'CPSIT.' . $_EXTKEY,
	'Pi1',
	[
		'Reservation' => 'new, show, create, edit, checkout, confirm, delete, newParticipant, createParticipant, removeParticipant,
		newBillingAddress,createBillingAddress,editBillingAddress,removeBillingAddress,update',
		'Participant' => 'edit,update'
	]
	,
	// non-cacheable actions
	[
		'Reservation' => 'new, show, create, edit, checkout, confirm, delete, newParticipant, createParticipant, removeParticipant,
		newBillingAddress,createBillingAddress,editBillingAddress,removeBillingAddress,update',
        'Participant' => 'edit,update'
	]
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers']['tx_t3eventsreservation_CloseBooking'] = 'CPSIT\\T3eventsReservation\\Command\\CloseBookingCommandController';
