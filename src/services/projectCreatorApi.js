import axios from "@nextcloud/axios";
import { generateUrl } from "@nextcloud/router";

function pdfUrl(organizationId) {
  return generateUrl(
    `/apps/projectcreatoraio/api/v1/organizations/${organizationId}/default-pdf`,
  );
}

function documentTypesUrl(organizationId, documentTypeId) {
  const suffix = documentTypeId ? `/${documentTypeId}` : "";
  return generateUrl(
    `/apps/projectcreatoraio/api/v1/organizations/${organizationId}/ocr/document-types${suffix}`,
  );
}

const requestConfig = {
  headers: {
    "OCS-APIRequest": "true",
  },
};

export async function getOrganizationPdfInfo(organizationId) {
  const response = await axios.get(pdfUrl(organizationId), requestConfig);
  return response.data;
}

export async function uploadOrganizationPdf(organizationId, file, fileName) {
  const formData = new FormData();
  formData.append("pdf", file);
  formData.append("fileName", fileName);

  const response = await axios.post(pdfUrl(organizationId), formData, requestConfig);
  return response.data;
}

export async function deleteOrganizationPdf(organizationId) {
  const response = await axios.delete(pdfUrl(organizationId), requestConfig);
  return response.data;
}

export async function listOrganizationDocumentTypes(organizationId) {
  const response = await axios.get(documentTypesUrl(organizationId), {
    ...requestConfig,
    params: { include_inactive: 1 },
  });
  return (response.data && response.data.document_types) || [];
}

export async function createOrganizationDocumentType(organizationId, payload) {
  const response = await axios.post(documentTypesUrl(organizationId), payload, requestConfig);
  return response.data;
}

export async function updateOrganizationDocumentType(organizationId, documentTypeId, payload) {
  const response = await axios.put(
    documentTypesUrl(organizationId, documentTypeId),
    payload,
    requestConfig,
  );
  return response.data;
}

export async function deleteOrganizationDocumentType(organizationId, documentTypeId) {
  const response = await axios.delete(
    documentTypesUrl(organizationId, documentTypeId),
    requestConfig,
  );
  return response.data;
}
