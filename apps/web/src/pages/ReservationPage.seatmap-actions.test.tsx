import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter } from "react-router-dom";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ReservationPage } from "./ReservationPage";
import type { PublicEvent, PublicEventSeatMap } from "../lib/eventsClient";

const { mockFetchPublicEventBySlug, mockFetchPublicEventSeatMap } = vi.hoisted(
  () => ({
    mockFetchPublicEventBySlug: vi.fn(),
    mockFetchPublicEventSeatMap: vi.fn(),
  }),
);

vi.mock("../lib/eventsClient", async () => {
  const actual = await vi.importActual<typeof import("../lib/eventsClient")>(
    "../lib/eventsClient",
  );

  return {
    ...actual,
    fetchPublicEventBySlug: mockFetchPublicEventBySlug,
    fetchPublicEventSeatMap: mockFetchPublicEventSeatMap,
  };
});

vi.mock("../lib/reservationsClient", () => ({
  createReservation: vi.fn(),
}));

vi.mock("../lib/ticketStorage", () => ({
  saveLatestTicket: vi.fn(),
}));

const baseEvent: PublicEvent = {
  id: 1,
  slug: "midnight-resonance-2-0",
  title: "Midnight Resonance 2.0",
  summary: "A night-forward showcase of sound, visuals, and architecture.",
  city: "Casablanca",
  location: "The Glass Pavilion",
  starts_at: "2026-04-20T18:30:00+00:00",
  seats_total: 300,
  seats_available: 160,
  category: "Immersive Concert",
  price_amount: 79,
  currency: "USD",
  visual_tone: "neon-night",
};

const buildSeatMap = (items: PublicEventSeatMap["items"]): PublicEventSeatMap => ({
  event_slug: "midnight-resonance-2-0",
  total_seats: 300,
  available_seats: items.filter((item) => item.status === "available").length,
  layout: {
    columns: 12,
    rows: 25,
  },
  items,
});

const initialSeatMap = buildSeatMap([
  { label: "A-01", status: "available" },
  { label: "A-02", status: "available" },
  { label: "A-03", status: "available" },
  { label: "A-04", status: "available" },
  { label: "A-05", status: "available" },
  { label: "A-06", status: "available" },
]);

const refreshedSeatMap = buildSeatMap([
  { label: "A-01", status: "reserved" },
  { label: "A-02", status: "available" },
  { label: "A-03", status: "available" },
  { label: "A-04", status: "available" },
  { label: "A-05", status: "available" },
  { label: "A-06", status: "available" },
]);

const renderReservationPage = () => {
  render(
    <MemoryRouter initialEntries={["/reservation?event=midnight-resonance-2-0"]}>
      <ReservationPage />
    </MemoryRouter>,
  );
};

describe("ReservationPage seat-map action bar", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockFetchPublicEventBySlug.mockResolvedValue(baseEvent);
    mockFetchPublicEventSeatMap.mockResolvedValue(initialSeatMap);
  });

  it("lets users auto-select then reset seats", async () => {
    const user = userEvent.setup();
    renderReservationPage();

    await screen.findByRole("button", {
      name: `Select best 4`,
    });

    await user.click(
      screen.getByRole("button", {
        name: `Select best 4`,
      }),
    );

    await waitFor(() => {
      expect(screen.getByText("Selected seats: A-01, A-02, A-03, A-04")).toBeTruthy();
    });

    await user.click(
      screen.getByRole("button", {
        name: `Reset selection`,
      }),
    );

    await waitFor(() => {
      expect(screen.getByText("Selected seats: A-01")).toBeTruthy();
    });
  });

  it("shows a success toast after refreshing availability", async () => {
    const user = userEvent.setup();
    mockFetchPublicEventSeatMap.mockResolvedValueOnce(initialSeatMap);
    mockFetchPublicEventSeatMap.mockResolvedValueOnce(refreshedSeatMap);

    renderReservationPage();

    await screen.findByRole("button", {
      name: `Refresh availability`,
    });

    await user.click(
      screen.getByRole("button", {
        name: `Refresh availability`,
      }),
    );

    await waitFor(() => {
      expect(screen.getByText("Seat availability refreshed.")).toBeTruthy();
    });

    expect(mockFetchPublicEventSeatMap).toHaveBeenCalledTimes(2);
  });

  it("shows an error toast when refresh fails", async () => {
    const user = userEvent.setup();
    mockFetchPublicEventSeatMap.mockResolvedValueOnce(initialSeatMap);
    mockFetchPublicEventSeatMap.mockRejectedValueOnce(new Error("network_failed"));

    renderReservationPage();

    await screen.findByRole("button", {
      name: `Refresh availability`,
    });

    await user.click(
      screen.getByRole("button", {
        name: `Refresh availability`,
      }),
    );

    await waitFor(() => {
      expect(
        screen.getByText("Unable to refresh seats right now. Try again in a moment."),
      ).toBeTruthy();
    });

    expect(screen.getByText("Selected seats: A-01")).toBeTruthy();
  });
});
