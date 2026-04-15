<?php

declare(strict_types=1);

namespace OCA\SuperAdminPage\Controller;

use OCA\SuperAdminPage\Service\OrgOverviewService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

class DashboardController extends Controller {

    private IUserSession $userSession;
    private IGroupManager $groupManager;
    private OrgOverviewService $orgOverview;

    public function __construct(
        string $appName,
        IRequest $request,
        IUserSession $userSession,
        IGroupManager $groupManager,
        OrgOverviewService $orgOverview,
    ) {
        parent::__construct($appName, $request);
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
        $this->orgOverview = $orgOverview;
    }

    /**
     * @NoCSRFRequired
     */
    public function getData(): JSONResponse {
        if (($forbidden = $this->requireAdmin()) !== null) {
            return $forbidden;
        }
        return new JSONResponse([]);
    }

    /**
     * @NoCSRFRequired
     */
    public function listOrgs(): JSONResponse {
        if (($forbidden = $this->requireAdmin()) !== null) {
            return $forbidden;
        }
        return new JSONResponse(['orgs' => $this->orgOverview->listOrgs()]);
    }

    /**
     * @NoCSRFRequired
     */
    public function getOrg(int $orgId): JSONResponse {
        if (($forbidden = $this->requireAdmin()) !== null) {
            return $forbidden;
        }
        $data = $this->orgOverview->getOrgOverview($orgId);
        if ($data === null) {
            return new JSONResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
        }
        return new JSONResponse($data);
    }

    /**
     * @NoCSRFRequired
     */
    public function listBackups(): JSONResponse {
        if (($forbidden = $this->requireAdmin()) !== null) {
            return $forbidden;
        }
        return new JSONResponse([]);
    }

    /**
     * @NoCSRFRequired
     */
    public function listAho(): JSONResponse {
        if (($forbidden = $this->requireAdmin()) !== null) {
            return $forbidden;
        }
        return new JSONResponse([]);
    }

    /**
     * @NoCSRFRequired
     */
    public function listSubscriptions(): JSONResponse {
        if (($forbidden = $this->requireAdmin()) !== null) {
            return $forbidden;
        }
        return new JSONResponse([]);
    }

    private function requireAdmin(): ?JSONResponse {
        $user = $this->userSession->getUser();
        if ($user === null || !$this->groupManager->isAdmin($user->getUID())) {
            return new JSONResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
        }
        return null;
    }
}
