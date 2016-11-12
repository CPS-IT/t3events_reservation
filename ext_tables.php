<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

// register frontend plugin
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
	'CPSIT.' . $_EXTKEY,
	'Pi1',
	'Reservations'
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'Reservations');

if (TYPO3_MODE === 'BE') {
	\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
		'CPSIT.' . $_EXTKEY,
		'Events',
		'm1',
		'',
		[
			'Backend\Bookings' => 'list, show, edit, update, cancel, delete, newParticipant, createParticipant,
			editParticipant, removeParticipant, newNotification, createNotification, reset, download',
		],
		[
			'access' => 'user,group',
			'icon' => 'EXT:' . $_EXTKEY . '/Resources/Public/Icons/module_icon_reservation.png',
			'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_m1.xlf',
		]
	);

	\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
		'CPSIT.' . $_EXTKEY,
		'Events',
        'm3',
        '',
        [
			'Backend\Participant' => 'list, download,reset',
		],
		[
			'access' => 'user,group',
			'icon' => 'EXT:' . $_EXTKEY . '/Resources/Public/Icons/module_icon_participant.png',
			'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_m3.xlf',
		]
	);
}
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr(
	'tx_t3eventsreservation_domain_model_reservation',
	'EXT:t3events_reservation/Resources/Private/Language/locallang_csh_tx_t3eventsreservation_domain_model_reservation.xlf');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_t3eventsreservation_domain_model_reservation');

// add sprite icons
if (class_exists(\TYPO3\CMS\Backend\Sprite\SpriteManager::class)) {
    $icons = [
        'download-excel-white' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath($_EXTKEY) . 'Resources/Public/Icons/icon_excel_white.png',
        'download-excel-blue' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath($_EXTKEY) . 'Resources/Public/Icons/icon_excel_blue.png',
    ];
    \TYPO3\CMS\Backend\Sprite\SpriteManager::addSingleIcons(
        $icons,
        $_EXTKEY
    );
}
if (class_exists(\TYPO3\CMS\Core\Imaging\IconRegistry::class)) {
    $icons = [
        'download-excel-white' => 'Resources/Public/Icons/icon_excel_white.png',
        'download-excel-blue' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath($_EXTKEY) . 'Resources/Public/Icons/icon_excel_blue.png',
    ];
    /** @var \TYPO3\CMS\Core\Imaging\IconRegistry $iconRegistry */
    $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
    foreach ($icons as $identifier=>$path) {
        $iconRegistry->registerIcon(
            $identifier,
            \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
            ['source' => 'EXT:' . $_EXTKEY . $path]
        );
    }
}
