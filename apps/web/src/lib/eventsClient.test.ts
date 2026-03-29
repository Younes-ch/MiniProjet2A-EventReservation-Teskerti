import { afterEach, describe, expect, it, vi } from "vitest";
import { deleteAdminEvent, fetchPublicEvents } from "./eventsClient";

describe("fetchPublicEvents", () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("does not set content-type for GET list fetch", async () => {
    const fetchSpy = vi.spyOn(globalThis, "fetch").mockResolvedValue({
      ok: true,
      json: vi.fn().mockResolvedValue({
        items: [],
      }),
    } as unknown as Response);

    await fetchPublicEvents();

    const requestInit = fetchSpy.mock.calls[0]?.[1];
    const headers = new Headers(requestInit?.headers);
    expect(headers.has("Content-Type")).toBe(false);
  });

  it("throws when the response shape has no items array", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue({
      ok: true,
      json: vi.fn().mockResolvedValue({}),
    } as unknown as Response);

    await expect(fetchPublicEvents()).rejects.toThrow("invalid_response_shape");
  });

  it("accepts 204 no-content responses for delete actions", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue({
      ok: true,
      status: 204,
      json: vi.fn().mockRejectedValue(new Error("Unexpected end of JSON input")),
    } as unknown as Response);

    await expect(deleteAdminEvent("demo-token", 42)).resolves.toEqual({});
  });
});
