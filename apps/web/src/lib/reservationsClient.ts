export type CreateReservationPayload = {
  event_slug: string;
  full_name: string;
  email: string;
  phone: string;
};

export type ReservationResponse = {
  reservation_id: string;
  attendee_name: string;
  attendee_email: string;
  attendee_phone: string;
  event_slug: string;
  event_title: string;
  event_date: string;
  event_time: string;
  event_location: string;
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

export const createReservation = (payload: CreateReservationPayload) =>
  requestJson<ReservationResponse>("/api/reservations", {
    method: "POST",
    body: JSON.stringify(payload),
  });
