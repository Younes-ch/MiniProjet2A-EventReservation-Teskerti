export type PublicEvent = {
  id: number;
  slug: string;
  title: string;
  summary: string;
  category: string;
  location: string;
  city: string;
  starts_at: string;
  price_amount: number;
  currency: string;
  seats_total: number;
  seats_available: number;
  visual_tone: string;
};

type EventsListResponse = {
  items: PublicEvent[];
};

export type UpsertEventPayload = {
  title: string;
  summary: string;
  category: string;
  location: string;
  city: string;
  starts_at: string;
  price_amount: number;
  currency: string;
  seats_total: number;
  seats_available: number;
  visual_tone: string;
};

export type PublicEventSeatMapItem = {
  label: string;
  status: "available" | "reserved";
};

export type PublicEventSeatMap = {
  event_slug: string;
  total_seats: number;
  available_seats: number;
  layout: {
    columns: number;
    rows: number;
  };
  items: PublicEventSeatMapItem[];
};

const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL ?? "").replace(/\/$/, "");

const buildApiUrl = (path: string) => `${API_BASE_URL}${path}`;

const extractErrorMessage = (data: unknown): string | null => {
  if (!data || typeof data !== "object") {
    return null;
  }

  const candidate = (data as { error?: unknown }).error;
  if (typeof candidate === "string" && candidate.trim().length > 0) {
    return candidate;
  }

  return null;
};

const requestJson = async <T>(path: string, init?: RequestInit): Promise<T> => {
  const response = await fetch(buildApiUrl(path), {
    ...init,
    headers: {
      "Content-Type": "application/json",
      ...(init?.headers ?? {}),
    },
  });

  const payload = await response
    .json()
    .catch(() => ({} as Record<string, unknown>));

  if (!response.ok) {
    const message = extractErrorMessage(payload) ?? "request_failed";
    throw new Error(message);
  }

  return payload as T;
};

export const fetchPublicEvents = async (): Promise<PublicEvent[]> => {
  const response = await requestJson<EventsListResponse>("/api/events");
  return response.items;
};

export const fetchPublicEventBySlug = (eventSlug: string) =>
  requestJson<PublicEvent>(`/api/events/${encodeURIComponent(eventSlug)}`);

export const fetchPublicEventSeatMap = (eventSlug: string) =>
  requestJson<PublicEventSeatMap>(
    `/api/events/${encodeURIComponent(eventSlug)}/seats`,
  );

export const fetchAdminEvents = async (
  accessToken: string,
): Promise<PublicEvent[]> => {
  const response = await requestJson<EventsListResponse>("/api/admin/events", {
    headers: {
      Authorization: `Bearer ${accessToken}`,
    },
  });

  return response.items;
};

export const createAdminEvent = (
  accessToken: string,
  payload: UpsertEventPayload,
) =>
  requestJson<PublicEvent>("/api/admin/events", {
    method: "POST",
    headers: {
      Authorization: `Bearer ${accessToken}`,
    },
    body: JSON.stringify(payload),
  });

export const updateAdminEvent = (
  accessToken: string,
  eventId: number,
  payload: UpsertEventPayload,
) =>
  requestJson<PublicEvent>(`/api/admin/events/${eventId}`, {
    method: "PUT",
    headers: {
      Authorization: `Bearer ${accessToken}`,
    },
    body: JSON.stringify(payload),
  });

export const deleteAdminEvent = (accessToken: string, eventId: number) =>
  requestJson<Record<string, never>>(`/api/admin/events/${eventId}`, {
    method: "DELETE",
    headers: {
      Authorization: `Bearer ${accessToken}`,
    },
  });
