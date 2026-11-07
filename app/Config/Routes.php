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
// InterLink background service -> NEXUS-L (no login: protected by the shared service key in the X-Service-Key header)
// ---------------------------------------------------------------------
$routes->post('interlink/service/callback', 'InterlinkServiceController::callback');
$routes->get('interlink/service/verify', 'InterlinkServiceController::verify');
$routes->post('interlink/service/lov', 'InterlinkLovController::receive');

// ---------------------------------------------------------------------
// Authenticated routes - nothing below is reachable without logging in
// ---------------------------------------------------------------------
$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'DashboardController::index', ['as' => 'dashboard']);
    $routes->get('dashboard/data', 'DashboardController::data');
    $routes->get('help', 'HelpController::index', ['as' => 'help']);
    $routes->get('analytics', 'AnalyticsController::index', ['as' => 'analytics']);
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
    $routes->post('interlink/report/grids', 'InterlinkController::saveGrids');
    $routes->post('interlink/sync', 'InterlinkController::sync');
    $routes->post('interlink/submit', 'InterlinkController::submitToInterlink');
    $routes->post('interlink/fetch-live-page', 'InterlinkController::fetchLivePage');
    $routes->post('interlink/pull-grid-html', 'InterlinkController::pullGridHtml');
    $routes->post('interlink/fetch-live-form', 'InterlinkController::fetchLiveForm');
    $routes->get('interlink/labrefs', 'InterlinkController::labrefs');
    $routes->get('interlink/search', 'InterlinkController::search');
    $routes->get('interlink/labref-detail', 'InterlinkController::labrefDetail');
    $routes->get('interlink/proxy', 'InterlinkController::proxy');
    $routes->post('interlink/proxy', 'InterlinkController::proxy');
    $routes->get('interlink/center/status', 'InterlinkController::centerStatus');
    $routes->post('interlink/center/fill', 'InterlinkController::centerFill');
    $routes->get('interlink/etrf', 'InterlinkController::etrf', ['as' => 'interlink_etrf']);
$routes->post('interlink/etrf-check', 'InterlinkController::etrfCheck');
$routes->get('interlink/etrf-preview', 'InterlinkController::etrfPreview');
    $routes->get('interlink/etrf-history', 'InterlinkController::etrfHistory');
    $routes->get('interlink/dsr-search', 'InterlinkController::dsrSearch');
    $routes->post('interlink/lov-sync', 'InterlinkLovController::sync');
    $routes->get('interlink/lov', 'InterlinkLovController::values');
    $routes->get('interlink/lov-status', 'InterlinkLovController::status');
    $routes->get('interlink/order-summary', 'InterlinkController::orderSummary');
    $routes->get('interlink/dsr-file', 'InterlinkController::dsrFile');
    $routes->post('interlink/dsr-sync', 'InterlinkController::dsrSync');
    $routes->post('interlink/center/etrf', 'InterlinkController::centerEtrf');
    $routes->post('interlink/service/etrf', 'InterlinkServiceController::submitEtrf');
    $routes->get('interlink/service/status', 'InterlinkServiceController::status');
    $routes->post('interlink/service/signin', 'InterlinkServiceController::signin');
    $routes->post('interlink/service/submit', 'InterlinkServiceController::submit');
    $routes->get('interlink/service/feed', 'InterlinkServiceController::feed');

    // QC Lab Digital Test Reports (#Digital Test Report & Bonding LIS System)
    $routes->get('dtr', 'DtrController::index', ['as' => 'dtr']);
    $routes->get('dtr/records', 'DtrController::records');
    $routes->get('dtr/record', 'DtrController::record');
    $routes->get('dtr/bonding-labrefs', 'DtrController::bondingLabRefs');
    $routes->get('dtr/standards', 'DtrController::standards');
    $routes->get('dtr/lookup', 'DtrController::lookup');
    $routes->get('dtr/shoe-lookup', 'DtrController::shoeLookup');
    $routes->get('dtr/shoe-search', 'DtrController::shoeSearch');
    $routes->get('dtr/retest-list', 'DtrController::retestList');
    $routes->post('dtr/wholeshoe-save', 'DtrController::wholeShoeSave');
    $routes->post('dtr/intake-save', 'DtrController::intakeSave');
    $routes->post('dtr/material-receive-save', 'DtrController::materialReceiveSave');
    $routes->post('dtr/load-machine', 'DtrController::loadMachine');
    $routes->post('dtr/save', 'DtrController::save');
    $routes->post('dtr/sheet-pdf', 'DtrController::sheetPdf');
    $routes->get('dtr/bonding-machine-pdf', 'DtrController::bondingMachinePdf');
    $routes->get('dtr/wholeshoe-report-pdf', 'DtrController::wholeShoeReportPdf');
    $routes->get('dtr/wholeshoe-labrefs', 'DtrController::wholeShoeLabRefs');
    $routes->get('dtr/wholeshoe-report', 'DtrController::wholeShoeReport');
    $routes->post('dtr/wholeshoe-report-save', 'DtrController::wholeShoeReportSave');
    // report sign-off before InterLink (QR approval by a registered Manager / Supervisor)
    $routes->get('dtr/report-pdf', 'ApprovalController::reportPdf');
    $routes->get('dtr/approval/status', 'ApprovalController::status');
    $routes->post('dtr/approval/request', 'ApprovalController::request');
    $routes->get('dtr/approval/file/(:segment)', 'ApprovalController::file/$1');
    $routes->get('dtr/approval/verify', 'ApprovalController::verify');
    $routes->post('dtr/approval/pdf-pages', 'ApprovalController::pdfPages');
    $routes->get('dtr/approval/feed', 'ApprovalController::feed');
    $routes->get('dtr/approval/name-format', 'ApprovalController::nameFormat');
    $routes->post('dtr/approval/name-format', 'ApprovalController::nameFormat');
    $routes->get('approve/(:segment)/pdf', 'ApprovalController::preview/$1');
    $routes->get('approve/(:segment)', 'ApprovalController::page/$1');
    $routes->post('approve/(:segment)', 'ApprovalController::decide/$1');
    $routes->get('dtr/layout', 'DesignerController::layout');

    $routes->get('dtr/bonding-access-pdf', 'DtrController::bondingAccessPdf');

    // Laboratory Documents: the registers of the "#" folders of the Lab Documents share
    $routes->get('labdocs', 'LabDocsController::index', ['as' => 'labdocs']);
    $routes->post('labdocs/presence', 'LabDocsController::presence');
    $routes->get('labdocs/(:segment)/m/(:segment)', 'LabDocsController::method/$1/$2');
    $routes->post('labdocs/(:segment)/m/(:segment)/save', 'LabDocsController::saveMethod/$1/$2');
    $routes->post('labdocs/(:segment)/m/(:segment)/restore', 'LabDocsController::restoreMethod/$1/$2');
    $routes->get('labdocs/(:segment)/pdf/(:segment)', 'LabDocsController::pdf/$1/$2');
    $routes->get('labdocs/(:segment)/ill/(:num)', 'LabDocsController::illustrated/$1/$2');
    $routes->get('labdocs/(:segment)/doc/(:segment)', 'LabDocsController::doc/$1/$2');
    $routes->get('labdocs/(:segment)/file/(:segment)', 'LabDocsController::file/$1/$2');
    $routes->get('labdocs/(:segment)/b/(:segment)', 'LabDocsController::book/$1/$2');
    $routes->get('labdocs/(:segment)/b/(:segment)/sheet/(:num)', 'LabDocsController::sheet/$1/$2/$3');
    $routes->post('labdocs/(:segment)/b/(:segment)/save', 'LabDocsController::saveBook/$1/$2');
    $routes->post('labdocs/(:segment)/rev/(:num)/delete', 'LabDocsController::deleteRev/$1/$2');
    $routes->get('labdocs/(:segment)/v/(:num)', 'LabDocsController::fileView/$1/$2');
    $routes->get('labdocs/(:segment)/f/(:num)', 'LabDocsController::fileGet/$1/$2');
    $routes->get('labdocs/(:segment)/thumb/(:num)', 'LabDocsController::thumb/$1/$2');
    $routes->get('labdocs/(:segment)', 'LabDocsController::show/$1');
    $routes->post('labdocs/(:segment)/save', 'LabDocsController::save/$1');

    // Material Testing & Cyclic Monitoring Suite (Marc)
    $routes->get('materials', 'MaterialsController::index', ['as' => 'materials']);
    $routes->get('material-testing', 'MaterialsController::index');

    // Whole Shoe Physical & Monitoring Test Suite (Marc)
    $routes->get('wholeshoe', 'WstController::wholeshoe', ['as' => 'wholeshoe']);
    $routes->get('whole-shoe-test', 'WstController::wholeshoe');

    // Test Results Summary (Material Testing & Whole Shoe Test Combined - Marc)
    $routes->get('test-summary', 'WstController::summary', ['as' => 'test-summary']);
    $routes->get('tests-summary', 'WstController::summary');
    $routes->get('test-results', 'WstController::summary');
    $routes->get('wst/summary-data', 'WstController::summaryData');
    $routes->get('wst/export-summary', 'WstController::exportSummary');

    // Whole Shoe Cyclic Test Execution & Monitoring API (Marc)
    $routes->get('wst/records', 'WstController::records');
    $routes->get('wst/record/(:num)', 'WstController::record/$1');
    $routes->get('wst/state/(:num)', 'WstController::state/$1');
    $routes->get('wst/next-labref', 'WstController::nextLabref');
    $routes->post('wst/create', 'WstController::create');
    $routes->post('wst/start/(:num)', 'WstController::start/$1');
    $routes->post('wst/stop/(:num)', 'WstController::stop/$1');
    $routes->post('wst/stop-sample/(:num)', 'WstController::stopSample/$1');
    $routes->post('wst/log-findings/(:num)', 'WstController::logFindings/$1');
    // Help bot (local, system-only): chat, activity events
    $routes->post('bot/ask', 'BotController::ask');
    $routes->post('bot/events', 'BotController::events');
    $routes->post('bot/feedback', 'BotController::feedback');
    $routes->get('bot/file/(:segment)', 'BotController::file/$1');
    $routes->post('bot/chart-pdf', 'BotController::chartPdf');
    $routes->post('bot/escalate', 'BotController::escalate');
    $routes->get('bot/thread', 'BotController::thread');
    $routes->get('bot/topic', 'BotController::topic');
    $routes->get('bot/suggest', 'BotController::suggest');
    $routes->get('bot/history', 'BotController::history');
    $routes->get('bot/badge', 'BotController::badge');
    $routes->post('bot/toggle-ai', 'BotController::toggleAi');
    $routes->post('brain/toggle', 'BotController::toggleAi');
});

// Report Designer: admins only.
$routes->group('', ['filter' => 'auth:admin'], static function (RouteCollection $routes): void {
    // Settings (Admin only): InterLink service address and key, the database in use and a transfer to another one, report defaults
    $routes->get('settings', 'SettingsController::index', ['as' => 'settings']);
    $routes->post('settings/save', 'SettingsController::save');
    $routes->post('settings/service/test', 'SettingsController::testService');
    $routes->post('settings/ai/test', 'SettingsController::testAi');
    $routes->post('settings/ai/seed', 'SettingsController::seedAiKnowledge');
    $routes->post('settings/git/sync', 'SettingsController::gitSync');
    $routes->post('settings/db/test', 'SettingsController::dbTest');
    $routes->post('settings/db/switch', 'SettingsController::dbSwitch');
    $routes->post('settings/transfer/start', 'SettingsController::transferStart');
    $routes->get('settings/transfer/status', 'SettingsController::transferStatus');
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
    $routes->get('brain/live', 'BotController::live');
    $routes->get('brain/session/(:num)', 'BotController::session/$1');
    $routes->post('brain/session/close', 'BotController::sessionClose');
    $routes->post('brain/resolve', 'BotController::resolve');
    $routes->get('brain/learn-draft', 'BotController::learnDraft');
    $routes->get('brain/knowledge', 'BotController::knowledge');
    $routes->post('brain/knowledge/save', 'BotController::knowledgeSave');
    $routes->post('brain/knowledge/status', 'BotController::knowledgeStatus');
    $routes->post('brain/import', 'BotController::import');
    $routes->get('brain/unanswered', 'BotController::unanswered');
    $routes->post('brain/unanswered/answer', 'BotController::unansweredAnswer');
    $routes->get('brain/universe/graph', 'BotController::universeGraph');
    $routes->get('brain/universe/feed', 'BotController::universeFeed');
    $routes->get('brain/ai/status', 'BotController::aiStatus');
    $routes->post('brain/ai/train', 'BotController::aiTrain');
    $routes->get('brain/ai/review', 'BotController::aiReview');
    $routes->post('brain/ai/review/seed', 'BotController::aiReviewSeed');
    $routes->post('brain/ai/label', 'BotController::aiLabel');
    $routes->get('brain/learn', 'BotController::learn');
    $routes->post('brain/learn/decide', 'BotController::learnDecide');
    $routes->get('brain/issues', 'BotController::issues');
    $routes->post('brain/issues/resolve', 'BotController::issuesResolve');
    $routes->get('brain/health', 'BotController::health');
    $routes->post('brain/flush', 'BotController::flushNow');
    $routes->post('brain/restore', 'BotController::restoreNow');
    $routes->get('designer/settings', 'DesignerController::settings');
    $routes->post('designer/settings/save', 'DesignerController::saveSettings');
    $routes->post('designer/settings/test', 'DesignerController::testSettings');
    $routes->get('designer/signatories', 'ApprovalController::signatories');
    $routes->post('designer/signatories/save', 'ApprovalController::saveSignatory');
    $routes->post('designer/signatories/delete', 'ApprovalController::deleteSignatory');
});
