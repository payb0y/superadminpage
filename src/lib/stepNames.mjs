/**
 * The name a Deck step (stack) is shown under.
 *
 * Boards made before the Dutch translation keep the English names of the six
 * standard steps ("Next priority", "Approved", …, and "Approved/Done" on the
 * oldest boards). Those are shown in the user's language; a step the team
 * named itself is shown as typed. The stored title, which filters and counts
 * compare against, is never changed. Keys carry the "step" context because
 * "In progress" and "Done" mean something else elsewhere in this app.
 */
import { tc } from "./l10n.mjs";

const STANDARD_STEPS = new Map([
  ["process steps", "Process steps"],
  ["next priority", "Next priority"],
  ["in progress", "In progress"],
  ["to review", "To review"],
  ["approved", "Approved"],
  ["done", "Done"],
  ["approved/done", "Approved/Done"],
]);

export function stepDisplayName(title) {
  const value = String(title ?? "");
  const standard = STANDARD_STEPS.get(value.trim().toLowerCase());
  return standard ? tc(standard, "step") : value;
}
