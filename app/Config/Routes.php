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
});
