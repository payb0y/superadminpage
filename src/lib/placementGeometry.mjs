export function clamp(value, minimum, maximum) {
  return Math.max(minimum, Math.min(maximum, value));
}

export function resizeField(field, requestedWidth, aspectRatio, page) {
  let width = clamp(requestedWidth, 48, page.width - field.x);
  let height = width / aspectRatio;
  if (field.y + height > page.height) {
    height = page.height - field.y;
    width = height * aspectRatio;
  }
  return { width, height };
}

export function serializeField(field) {
  return {
    elementId: field.elementId,
    fileId: field.fileId,
    signRequestId: field.signRequestId,
    type: "signature",
    coordinates: {
      page: field.page,
      left: Math.floor(field.x),
      top: Math.floor(field.y),
      width: Math.floor(field.width),
      height: Math.floor(field.height),
    },
  };
}
