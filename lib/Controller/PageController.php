<?php

declare(strict_types=1);

namespace OCA\SuperAdminPage\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Util;

class PageController extends Controller {

    private IUserSession $userSession;
    private IGroupManager $groupManager;

    public function __construct(
        string $appName,
        IRequest $request,
        IUserSession $userSession,
        IGroupManager $groupManager,
    ) {
        parent::__construct($appName, $request);
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
    }

    /**
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        $user = $this->userSession->getUser();
        if ($user === null || !$this->groupManager->isAdmin($user->getUID())) {
            return new TemplateResponse('superadminpage', 'index', [], TemplateResponse::RENDER_AS_ERROR);
        }

        Util::addScript('superadminpage', 'superadminpage-main');
        return new TemplateResponse('superadminpage', 'index');
    }
}
