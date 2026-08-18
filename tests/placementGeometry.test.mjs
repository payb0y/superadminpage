import test from "node:test";
import assert from "node:assert/strict";
import { clamp, resizeField, serializeField } from "../src/lib/placementGeometry.mjs";

test("clamp keeps fields within page bounds", () => {
  assert.equal(clamp(-5, 0, 100), 0);
  assert.equal(clamp(40, 0, 100), 40);
  assert.equal(clamp(125, 0, 100), 100);
});

test("resize preserves aspect ratio and page bounds", () => {
  const field = { x: 400, y: 700 };
  const result = resizeField(field, 300, 3, { width: 595, height: 842 });
  assert.equal(result.width, 195);
  assert.equal(result.height, 65);

  const bottomBounded = resizeField({ x: 20, y: 820 }, 150, 3, { width: 595, height: 842 });
  assert.equal(bottomBounded.height, 22);
  assert.equal(bottomBounded.width, 66);
});

test("serialization matches LibreSign top-left integer coordinates", () => {
  assert.deepEqual(serializeField({
    elementId: 9,
    fileId: 12,
    signRequestId: 15,
    page: 2,
    x: 0.9,
    y: 24.8,
    width: 150.9,
    height: 50.4,
  }), {
    elementId: 9,
    fileId: 12,
    signRequestId: 15,
    type: "signature",
    coordinates: { page: 2, left: 0, top: 24, width: 150, height: 50 },
  });
});
