<?php

declare(strict_types=1);

namespace OCA\SuperAdminPage\Controller;

use OCA\SuperAdminPage\Service\ActivityService;
use OCA\SuperAdminPage\Service\FinancialsService;
use OCA\SuperAdminPage\Service\GeocodeService;
use OCA\SuperAdminPage\Service\OrgOverviewService;
use OCA\SuperAdminPage\Service\PlatformService;
use OCA\SuperAdminPage\Service\ProjectTasksService;
use OCA\SuperAdminPage\Service\SystemHealthService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class DashboardController extends Controller {

    private IUserSession $userSession;
    private IGroupManager $groupManager;
    private OrgOverviewService $orgOverview;
    private PlatformService $platform;
    private ProjectTasksService $projectTasks;
    private ActivityService $activity;
    private SystemHealthService $systemHealth;
    private GeocodeService $geocode;
    private FinancialsService $financials;
    private LoggerInterface $logger;

    public function __construct(
        string $appName,
        IRequest $request,
        IUserSession $userSession,
        IGroupManager $groupManager,
        OrgOverviewService $orgOverview,
        PlatformService $platform,
        ProjectTasksService $projectTasks,
        ActivityService $activity,
        SystemHealthService $systemHealth,
        GeocodeService $geocode,
        FinancialsService $financials,
        LoggerInterface $logger,
    ) {
        parent::__construct($appName, $request);
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
        $this->orgOverview = $orgOverview;
        $this->platform = $platform;
        $this->projectTasks = $projectTasks;
        $this->activity = $activity;
        $this->systemHealth = $systemHealth;
        $this->geocode = $geocode;
        $this->financials = $financials;
        $this->logger = $logger;
    }

    /**
     * @NoCSRFRequired
     */
    public function getData(): JSONResponse {
        return $this->guard(fn () => new JSONResponse($this->platform->getOverview()));
    }

    /**
     * @NoCSRFRequired
     */
    public function listOrgs(): JSONResponse {
        return $this->guard(fn () => new JSONResponse(['orgs' => $this->orgOverview->listOrgs()]));
    }

    /**
     * @NoCSRFRequired
     */
    public function getOrg(int $orgId): JSONResponse {
        return $this->guard(function () use ($orgId) {
            $data = $this->orgOverview->getOrgOverview($orgId);
            if ($data === null) {
                return new JSONResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
            }
            return new JSONResponse($data);
        });
    }

    /**
     * @NoCSRFRequired
     */
    public function getProjectTasks(int $projectId): JSONResponse {
        return $this->guard(function () use ($projectId) {
            $data = $this->projectTasks->getTasksForProject($projectId);
            if ($data === null) {
                return new JSONResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
            }
            return new JSONResponse($data);
        });
    }

    /**
     * @NoCSRFRequired
     */
    public function getProjectGeocode(int $projectId): JSONResponse {
        return $this->guard(function () use ($projectId) {
        $result = $this->geocode->geocodeProject($projectId);
        switch ($result['status']) {
            case 'no_project':
                return new JSONResponse(['reason' => 'no_project'], Http::STATUS_NOT_FOUND);
            case 'no_address':
                return new JSONResponse(['reason' => 'no_address'], Http::STATUS_NOT_FOUND);
            case 'not_found':
                return new JSONResponse(['reason' => 'not_found'], Http::STATUS_NOT_FOUND);
            case 'unavailable':
                return new JSONResponse(
                    ['error' => 'geocoding_unavailable'],
                    Http::STATUS_SERVICE_UNAVAILABLE,
                );
            case 'ok':
                return new JSONResponse([
                    'lat'         => $result['lat'],
                    'lng'         => $result['lng'],
                    'displayName' => $result['displayName'] ?? null,
                    'source'      => $result['source'] ?? 'nominatim',
                    'fromCache'   => $result['fromCache'] ?? false,
                ]);
            default:
                return new JSONResponse(['error' => 'internal'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
        });
    }

    /**
     * @NoCSRFRequired
     */
    public function listBackups(): JSONResponse {
        return $this->guard(fn () => new JSONResponse([]));
    }

    /**
     * @NoCSRFRequired
     */
    public function listAho(): JSONResponse {
        return $this->guard(fn () => new JSONResponse([]));
    }

    /**
     * Backs the Financials tab. The route was already declared for
     * "subscription roster + history", which is exactly this payload plus the
     * month-by-month series derived from it, so it is implemented here rather
     * than given a second URL of its own.
     *
     * @NoCSRFRequired
     */
    public function listSubscriptions(): JSONResponse {
        return $this->guard(fn () => new JSONResponse($this->financials->getFinancials()));
    }

    /**
     * @NoCSRFRequired
     */
    public function getSystemHealth(): JSONResponse {
        return $this->guard(fn () => new JSONResponse($this->systemHealth->getSnapshot()));
    }

    /**
     * @NoCSRFRequired
     */
    public function getOrgActivity(int $orgId): JSONResponse {
        return $this->guard(function () use ($orgId) {
            [$sources, $page, $size, $filters] = $this->parseActivityQuery();
            return new JSONResponse($this->activity->listForOrg($orgId, $page, $size, $sources, $filters));
        });
    }

    /**
     * @NoCSRFRequired
     */
    public function getProjectActivity(int $orgId, int $projectId): JSONResponse {
        return $this->guard(function () use ($orgId, $projectId) {
            [$sources, $page, $size, $filters] = $this->parseActivityQuery();
            $stream = (string)$this->request->getParam('stream', 'in_project');

            if ($stream === 'org_wide') {
                return new JSONResponse($this->activity->listOrgWideForProjectView($orgId, $page, $size, $sources, $filters));
            }
            return new JSONResponse($this->activity->listForProject($orgId, $projectId, $page, $size, $sources, $filters));
        });
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

    /**
     * Gate + error boundary for every action. Enforces the admin check, then
     * runs the handler. Any exception is logged server-side and returned as a
     * sanitized JSON 500 — we never leak the exception message or stack trace
     * to the client (Nextcloud would otherwise serialize the full trace into
     * the response body for a plain Controller).
     *
     * @param callable(): JSONResponse $handler
     */
    private function guard(callable $handler): JSONResponse {
        if (($forbidden = $this->requireAdmin()) !== null) {
            return $forbidden;
        }
        try {
            return $handler();
        } catch (\Throwable $e) {
            $this->logger->error('superadminpage request failed: ' . $e->getMessage(), [
                'exception' => $e,
                'app' => 'superadminpage',
            ]);
            return new JSONResponse(
                [
                    'error' => 'internal',
                    'message' => 'Something went wrong loading this data. Please try again in a moment.',
                ],
                Http::STATUS_INTERNAL_SERVER_ERROR,
            );
        }
    }
}
