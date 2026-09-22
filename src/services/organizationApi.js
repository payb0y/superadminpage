import axios from "@nextcloud/axios";
import { generateUrl } from "@nextcloud/router";

const OCS_HEADERS = {
  "OCS-APIRequest": "true",
  Accept: "application/json",
  "Content-Type": "application/json",
};

function teamsUrl(organizationId, teamId) {
  const suffix = teamId == null ? "" : `/${teamId}`;
  return generateUrl(
    `/ocs/v2.php/apps/organization/organizations/${organizationId}/teams${suffix}`,
  );
}

function projectTeamsUrl(organizationId, projectId) {
  if (projectId == null) {
    return generateUrl(
      `/ocs/v2.php/apps/organization/organizations/${organizationId}/project-teams`,
    );
  }
  return generateUrl(
    `/ocs/v2.php/apps/organization/organizations/${organizationId}/projects/${projectId}/team`,
  );
}

function requestConfig() {
  return { headers: OCS_HEADERS, params: { format: "json" } };
}

function unwrap(response) {
  const data = response && response.data;
  if (!data || !data.ocs || data.ocs.data === undefined) {
    throw new Error("The server returned an invalid organization response.");
  }
  return data.ocs.data;
}

function readableError(error) {
  if (!error || !error.response) {
    return error && error.message ? error.message : "Couldn't reach the server. Try again.";
  }

  const responseData = error.response.data || {};
  const message =
    responseData.message ||
    (responseData.ocs && responseData.ocs.meta && responseData.ocs.meta.message);
  if (message) return String(message);
  return "Request failed (HTTP " + error.response.status + ")";
}

function normalizeProjectAssignments(projectTeams) {
  if (!Array.isArray(projectTeams)) {
    throw new Error("The server returned an invalid project assignment list.");
  }
  return projectTeams.map(function (assignment) {
    var team = assignment && assignment.team;
    var assignmentId = null;
    if (assignment && assignment.assignmentId != null) {
      assignmentId = Number(assignment.assignmentId);
    }
    return Object.assign({}, assignment, {
      projectId: Number(assignment && assignment.projectId),
      assignmentId: assignmentId,
      team: team
        ? Object.assign({}, team, { id: Number(team.id) })
        : null,
    });
  });
}

async function request(method, url, payload) {
  try {
    const config = requestConfig();
    let response;
    if (payload === undefined) {
      response = await axios[method](url, config);
    } else {
      response = await axios[method](url, payload, config);
    }
    return unwrap(response);
  } catch (error) {
    throw new Error(readableError(error));
  }
}

export async function listOrganizationTeams(organizationId) {
  const data = await request("get", teamsUrl(organizationId));
  if (!Array.isArray(data.teams)) {
    throw new Error("The server returned an invalid team list.");
  }
  return data.teams;
}

export async function createOrganizationTeam(organizationId, payload) {
  const data = await request("post", teamsUrl(organizationId), payload);
  return data.team || data;
}

export async function updateOrganizationTeam(organizationId, teamId, payload) {
  const data = await request("put", teamsUrl(organizationId, teamId), payload);
  return data.team || data;
}

export async function deleteOrganizationTeam(organizationId, teamId) {
  return request("delete", teamsUrl(organizationId, teamId));
}

export async function addTeamMember(organizationId, teamId, userId) {
  const data = await request(
    "post",
    `${teamsUrl(organizationId, teamId)}/members`,
    { userId: userId },
  );
  return data.team || data;
}

export async function removeTeamMember(organizationId, teamId, userId) {
  const data = await request(
    "delete",
    `${teamsUrl(organizationId, teamId)}/members/${encodeURIComponent(userId)}`,
  );
  return data.team || data;
}

export async function listProjectTeamAssignments(organizationId) {
  const data = await request("get", projectTeamsUrl(organizationId));
  return normalizeProjectAssignments(data.projectTeams);
}

export async function assignProjectTeam(organizationId, projectId, teamId) {
  const data = await request(
    "put",
    projectTeamsUrl(organizationId, projectId),
    { teamId: teamId == null ? null : Number(teamId) },
  );
  const assignments = normalizeProjectAssignments(data.projectTeams);
  const persisted = assignments.find(function (assignment) {
    return assignment.projectId === Number(projectId);
  });
  const persistedTeamId = persisted && persisted.team ? persisted.team.id : null;
  const requestedTeamId = teamId == null ? null : Number(teamId);
  if (!persisted || persistedTeamId !== requestedTeamId) {
    throw new Error("The server did not persist the requested project assignment.");
  }
  return assignments;
}
