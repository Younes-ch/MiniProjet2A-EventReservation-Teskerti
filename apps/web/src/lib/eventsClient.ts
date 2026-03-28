export type PublicEvent = {
  id: string;
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