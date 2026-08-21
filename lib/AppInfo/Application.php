<?php

declare(strict_types=1);

namespace OCA\SuperAdminPage\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\IGroupManager;
use OCP\L10N\IFactory;

/**
 * Registers the navigation entry only for Nextcloud administrators.
 *
 * The entry used to be declared statically in info.xml, which shows it to every
 * signed-in account. Every route in this app already rejects non-admins — the
 * page controller through core's admin check and the API through requireAdmin()
 * — so this is not what keeps the data safe; it stops advertising a button that
 * only answers 403 to most of the people who can see it.
 *
 * Registering here rather than filtering in the sidebar app also covers the
 * places custom_layout does not draw: core's app menu and the accessibility
 * navigation both read INavigationManager.
 */
class Application extends App implements IBootstrap {

    public const APP_ID = 'superadminpage';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
    }

    public function boot(IBootContext $context): void {
        $context->injectFn(function (
            INavigationManager $navigationManager,
            IUserSession $userSession,
            IGroupManager $groupManager,
            IURLGenerator $urlGenerator,
            IFactory $l10nFactory,
        ): void {
            /**
            * The entry is registered unconditionally and decides for itself when the
            * navigation is actually built.
            *
            * boot() cannot do the check: on the OCS route the app menu is fetched from
            * (/ocs/v2.php/core/navigation/apps) apps are booted before the session is
            * resolved, so IUserSession::getUser() is null there and a check at boot
            * time hides the entry from everyone. A closure is evaluated later, from
            * NavigationManager::init(), by which point the user is known.
            *
            * Declining is expressed as a type other than 'link' because
            * INavigationManager::add() has no way to say "no entry" — it reads
            * $entry['id'] straight off the return value, so null would be fatal — and
            * getAll('link'), which is what builds the app menu, filters on exactly
            * that field.
            */
            $navigationManager->add(function () use ($userSession, $groupManager, $urlGenerator, $l10nFactory): array {
                $entry = [
                    'id'    => self::APP_ID,
                    'order' => 11,
                    'href'  => $urlGenerator->linkToRoute(self::APP_ID . '.page.index'),
                    'icon'  => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
                    'name'  => $l10nFactory->get(self::APP_ID)->t('Super Admin'),
                ];
                $user = $userSession->getUser();
                if ($user === null || !$groupManager->isAdmin($user->getUID())) {
                    $entry['type'] = 'hidden';
                }
                return $entry;
            });
        });
    }
}
