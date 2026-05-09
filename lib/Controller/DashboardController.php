<?php

declare(strict_types=1);

namespace OCA\SuperAdminPage\Controller;

use OCA\SuperAdminPage\Service\ActivityService;
use OCA\SuperAdminPage\Service\OrgOverviewService;
use OCA\SuperAdminPage\Service\PlatformService;
use OCA\SuperAdminPage\Service\ProjectTasksService;
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
    private PlatformService $platform;
    private ProjectTasksService $projectTasks;
    private ActivityService $activity;

    public function __construct(
        string $appName,
        IRequest $request,
        IUserSession $userSession,
        IGroupManager $groupManager,
        OrgOverviewService $orgOverview,
        PlatformService $platform,
        ProjectTasksService $projectTasks,
        ActivityService $activity,
    ) {
        parent::__construct($appName, $request);
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
        $this->orgOverview = $orgOverview;
        $this->platform = $platform;
        $this->projectTasks = $projectTasks;
        $this->activity = $activity;
    }

    /**
     * @NoCSRFRequired
     */
    public function getData(): JSONResponse {
        if (($forbidden = $this->requireAdmin()) !== null) {
            return $forbidden;
        }
        return new JSONResponse($this->platform->getOverview());
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
    public function getProjectTasks(int $projectId): JSONResponse {
        if (($forbidden = $this->requireAdmin()) !== null) {
            return $forbidden;
        }
        $data = $this->projectTasks->getTasksForProject($projectId);
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

    /**
     * @NoCSRFRequired
     */
    public function getOrgActivity(int $orgId): JSONResponse {
        if (($forbidden = $this->requireAdmin()) !== null) {
            return $forbidden;
        }
        [$sources, $page, $size, $filters] = $this->parseActivityQuery();
        return new JSONResponse($this->activity->listForOrg($orgId, $page, $size, $sources, $filters));
    }

    /**
     * @NoCSRFRequired
     */
    public function getProjectActivity(int $orgId, int $projectId): JSONResponse {
        if (($forbidden = $this->requireAdmin()) !== null) {
            return $forbidden;
        }
        [$sources, $page, $size, $filters] = $this->parseActivityQuery();
        $stream = (string)$this->request->getParam('stream', 'in_project');

        if ($stream === 'org_wide') {
            return new JSONResponse($this->activity->listOrgWideForProjectView($orgId, $page, $size, $sources, $filters));
        }
        return new JSONResponse($this->activity->listForProject($orgId, $projectId, $page, $size, $sources, $filters));
    }

    /**
     * @return array{0: array<int, string>, 1: int, 2: int, 3: array<string, mixed>}
     */
    private function parseActivityQuery(): array {
        $rawSources = (string)$this->request->getParam('sources', '');
        $sources = $rawSources !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $rawSources))))
            : [];

        $page = (int)$this->request->getParam('page', 1);
        if ($page < 1) { $page = 1; }

        $size = (int)$this->request->getParam('size', 50);
        if ($size <= 0) { $size = 50; }

        $from  = $this->request->getParam('from');
        $to    = $this->request->getParam('to');
        $actor = $this->request->getParam('actor');
        $q     = $this->request->getParam('q');

        $filters = [
            'fromTs' => ($from !== null && $from !== '') ? (int)$from : null,
            'toTs'   => ($to !== null && $to !== '')     ? (int)$to   : null,
            'actor'  => ($actor !== null && $actor !== '') ? (string)$actor : null,
            'q'      => ($q !== null && $q !== '')         ? (string)$q     : null,
        ];

        return [$sources, $page, $size, $filters];
    }

    private function requireAdmin(): ?JSONResponse {
        $user = $this->userSession->getUser();
        if ($user === null || !$this->groupManager->isAdmin($user->getUID())) {
            return new JSONResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
        }
        return null;
    }
}
