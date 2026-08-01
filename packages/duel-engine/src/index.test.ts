import { describe, expect, it } from "vitest";

import { duelEngineVersion } from "./index.js";

describe("duel-engine", () => {
  it("expõe sua versão", () => {
    expect(duelEngineVersion).toBe("0.0.0");
  });
});
