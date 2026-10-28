<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ---------------------------------------------------------------------
// Guest-only routes (login / registration / forgot password)
// ---------------------------------------------------------------------
$routes->group('', ['filter' => 'guest'], static function (RouteCollection $routes): void {
    $routes->get('login', 'AuthController::login', ['as' => 'login']);
    $routes->post('login', 'AuthController::attemptLogin');

    $routes->get('register', 'AuthController::register', ['as' => 'register']);
    $routes->post('register', 'AuthController::attemptRegister');

    $routes->get('forgot-password', 'PasswordResetController::forgot', ['as' => 'forgot']);
    $routes->post('forgot-password', 'PasswordResetController::sendCode');
    $routes->get('forgot-password/verify', 'PasswordResetController::verify', ['as' => 'forgot.verify']);
    $routes->post('forgot-password/verify', 'PasswordResetController::checkCode');
    $routes->post('forgot-password/resend', 'PasswordResetController::resend', ['as' => 'forgot.resend']);
    $routes->get('forgot-password/reset', 'PasswordResetController::reset', ['as' => 'forgot.reset']);
    $routes->post('forgot-password/reset', 'PasswordResetController::saveReset');
});

// ---------------------------------------------------------------------
// Authenticated routes - nothing below is reachable without logging in
// ---------------------------------------------------------------------
$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'DashboardController::index', ['as' => 'dashboard']);
    $routes->get('help', 'HelpController::index', ['as' => 'help']);
    $routes->get('/', static fn () => redirect()->route('dashboard'));
    $routes->post('logout', 'AuthController::logout', ['as' => 'logout']);

    $routes->get('inbox', 'InboxController::index', ['as' => 'inbox']);
    $routes->get('inbox/conversations', 'InboxController::conversations');
    $routes->get('inbox/unread', 'InboxController::unread');
    $routes->get('inbox/users', 'InboxController::users');
    $routes->get('inbox/thread/(:segment)', 'InboxController::thread/$1');
    $routes->post('inbox/send/(:segment)', 'InboxController::send/$1');
    $routes->post('inbox/upload/(:segment)', 'InboxController::upload/$1');
    $routes->get('inbox/file/(:segment)/(:num)', 'InboxController::file/$1/$2');
    $routes->post('inbox/react/(:segment)', 'InboxController::react/$1');
    $routes->post('inbox/edit/(:segment)', 'InboxController::edit/$1');
    $routes->post('inbox/bump/(:segment)', 'InboxController::bump/$1');
    $routes->post('inbox/forward/(:segment)', 'InboxController::forward/$1');
    $routes->post('inbox/pin/(:segment)', 'InboxController::pin/$1');
    $routes->get('inbox/details/(:segment)', 'InboxController::details/$1');
    $routes->post('inbox/setting/(:segment)', 'InboxController::setting/$1');
    $routes->get('inbox/pinned/(:segment)', 'InboxController::pinned/$1');
    $routes->get('inbox/chat-search/(:segment)', 'InboxController::chatSearch/$1');
    $routes->get('inbox/media/(:segment)', 'InboxController::media/$1');
    $routes->post('inbox/token', 'InboxController::saveToken');
    $routes->get('inbox/themes', 'InboxController::themes');
    $routes->post('inbox/group/create', 'InboxController::createGroup');
    $routes->post('inbox/group/approve/(:segment)', 'InboxController::approveGroup/$1');
    $routes->post('inbox/disappearing/(:segment)', 'InboxController::setDisappearing/$1');
    $routes->post('inbox/triggered/(:segment)/(:num)', 'InboxController::markTriggered/$1/$2');

    // Intertek InterLink 2.0 Report Automation & Sync
    $routes->get('interlink', 'InterlinkController::index', ['as' => 'interlink']);
    $routes->get('interlink/reports', 'InterlinkController::reports');
    $routes->get('interlink/report/(:segment)', 'InterlinkController::report/$1');
    $routes->post('interlink/report/save', 'InterlinkController::saveReport');
    $routes->post('interlink/sync', 'InterlinkController::sync');
    $routes->post('interlink/submit', 'InterlinkController::submitToInterlink');
    $routes->post('interlink/fetch-live-page', 'InterlinkController::fetchLivePage');
    $routes->post('interlink/fetch-live-form', 'InterlinkController::fetchLiveForm');
    $routes->get('interlink/labrefs', 'InterlinkController::labrefs');
    $routes->get('interlink/search', 'InterlinkController::search');
    $routes->get('interlink/labref-detail', 'InterlinkController::labrefDetail');
    $routes->get('interlink/proxy', 'InterlinkController::proxy');
    $routes->post('interlink/proxy', 'InterlinkController::proxy');
    $routes->get('interlink/center/status', 'InterlinkController::centerStatus');
    $routes->post('interlink/center/fill', 'InterlinkController::centerFill');

    // QC Lab Digital Test Reports (#Digital Test Report & Bonding LIS System)
    $routes->get('dtr', 'DtrController::index', ['as' => 'dtr']);
    $routes->get('dtr/records', 'DtrController::records');
    $routes->get('dtr/record', 'DtrController::record');
    $routes->get('dtr/standards', 'DtrController::standards');
    $routes->get('dtr/lookup', 'DtrController::lookup');
    $routes->post('dtr/load-machine', 'DtrController::loadMachine');
    $routes->post('dtr/save', 'DtrController::save');
    $routes->get('dtr/bonding-access-pdf', 'DtrController::bondingAccessPdf');
    $routes->post('dtr/sheet-pdf', 'DtrController::sheetPdf');
    $routes->get('dtr/layout', 'DesignerController::layout');
    // Help bot (local, system-only): chat, activity events
    $routes->post('bot/ask', 'BotController::ask');
    $routes->post('bot/events', 'BotController::events');
    $routes->post('bot/feedback', 'BotController::feedback');
    $routes->post('bot/escalate', 'BotController::escalate');
    $routes->get('bot/thread', 'BotController::thread');
    $routes->get('bot/topic', 'BotController::topic');
    $routes->get('bot/suggest', 'BotController::suggest');
    $routes->get('bot/history', 'BotController::history');
    $routes->get('bot/badge', 'BotController::badge');
});

// Report Designer: admins only.
$routes->group('', ['filter' => 'auth:admin'], static function (RouteCollection $routes): void {
    $routes->get('designer', 'DesignerController::index', ['as' => 'designer']);
    $routes->post('designer/save', 'DesignerController::save');
    $routes->post('designer/reset', 'DesignerController::reset');
    $routes->get('designer/maps', 'DesignerController::maps');
    $routes->post('designer/maps/save', 'DesignerController::saveMaps');
    $routes->get('designer/grid', 'DesignerController::grid');
    $routes->post('designer/maps/test', 'DesignerController::testMap');
    // Brain console (admins): answer escalated chats, teach the bot, review problems
    $routes->get('brain', 'BotController::console', ['as' => 'brain']);
    $routes->get('brain/overview', 'BotController::overview');
    $routes->get('brain/threads', 'BotController::threads');
    $routes->get('brain/thread/(:num)', 'BotController::threadMessages/$1');
    $routes->post('brain/reply', 'BotController::reply');
    $routes->post('brain/resolve', 'BotController::resolve');
    $routes->get('brain/learn-draft', 'BotController::learnDraft');
    $routes->get('brain/knowledge', 'BotController::knowledge');
    $routes->post('brain/knowledge/save', 'BotController::knowledgeSave');
    $routes->post('brain/knowledge/status', 'BotController::knowledgeStatus');
    $routes->post('brain/import', 'BotController::import');
    $routes->get('brain/unanswered', 'BotController::unanswered');
    $routes->post('brain/unanswered/answer', 'BotController::unansweredAnswer');
    $routes->get('brain/issues', 'BotController::issues');
    $routes->post('brain/issues/resolve', 'BotController::issuesResolve');
    $routes->get('brain/health', 'BotController::health');
    $routes->post('brain/flush', 'BotController::flushNow');
    $routes->post('brain/restore', 'BotController::restoreNow');
    $routes->get('designer/settings', 'DesignerController::settings');
    $routes->post('designer/settings/save', 'DesignerController::saveSettings');
    $routes->post('designer/settings/test', 'DesignerController::testSettings');
});
