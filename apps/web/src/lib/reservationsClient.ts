export type CreateReservationPayload = {
  event_slug: string;
  full_name: string;
  email: string;
  phone: string;
  seat_labels?: string[];
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
  seat_labels: string[];
  qr_code_token: string;
  ticket_download_url: string;
};

export type AdminReservationItem = {
  id: number;
  reservation_id: string;
  attendee_name: string;
  attendee_email: string;
  attendee_phone: string;
  seat_labels: string[];
  status: string;
  created_at: string;
  checked_in_at: string | null;
  event: {
    id: number | null;
    slug: string | null;
    title: string | null;
    location: string | null;
    city: string | null;
    starts_at: string | null;
  };
};

export type AdminReservationStatusFilter =
  | "all"
  | "confirmed"
  | "cancelled"
  | "waitlisted";

export type AdminReservationsQuery = {
  page?: number;
  perPage?: number;
  status?: AdminReservationStatusFilter;
  eventSlug?: string;
  query?: string;
};

export type AdminReservationsMeta = {
  page: number;
  per_page: number;
  total_items: number;
  total_pages: number;
  status: AdminReservationStatusFilter;
  query: string;
  event_slug: string;
};

type AdminReservationsListResponse = {
  items: AdminReservationItem[];
  meta: AdminReservationsMeta;
};

export type AdminReservationsListResult = AdminReservationsListResponse;

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
  const requestHeaders = new Headers(init?.headers ?? {});
  if (init?.body !== undefined && init.body !== null && !requestHeaders.has("Content-Type")) {
    requestHeaders.set("Content-Type", "application/json");
  }

  const response = await fetch(buildApiUrl(path), {
    ...init,
    headers: requestHeaders,
  });

  if (response.status === 204) {
    return {} as T;
  }

  let payload: unknown;

  try {
    payload = await response.json();
  } catch {
    if (!response.ok) {
      throw new Error("request_failed");
    }

    throw new Error("invalid_json_response");
  }

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

export const fetchAdminReservations = async (
  accessToken: string,
  query?: AdminReservationsQuery,
): Promise<AdminReservationsListResult> => {
  const queryParams = new URLSearchParams();

  if (query?.page !== undefined) {
    queryParams.set("page", query.page.toString());
  }

  if (query?.perPage !== undefined) {
    queryParams.set("per_page", query.perPage.toString());
  }

  if (query?.status && query.status !== "all") {
    queryParams.set("status", query.status);
  }

  if (query?.eventSlug && query.eventSlug.trim().length > 0) {
    queryParams.set("event_slug", query.eventSlug.trim());
  }

  if (query?.query && query.query.trim().length > 0) {
    queryParams.set("query", query.query.trim());
  }

  const queryString = queryParams.toString();

  const response = await requestJson<AdminReservationsListResponse>(
    `/api/admin/reservations${queryString.length > 0 ? `?${queryString}` : ""}`,
    {
      headers: {
        Authorization: `Bearer ${accessToken}`,
      },
    },
  );

  return response;
};

export const updateAdminReservationStatus = (
  accessToken: string,
  reservationId: number,
  status: "confirmed" | "cancelled" | "waitlisted",
) =>
  requestJson<AdminReservationItem>(
    `/api/admin/reservations/${reservationId}/status`,
    {
      method: "PATCH",
      headers: {
        Authorization: `Bearer ${accessToken}`,
      },
      body: JSON.stringify({
        status,
      }),
    },
  );

export const checkInAdminReservation = (
  accessToken: string,
  reservationId: string,
  qrCodeToken: string,
) =>
  requestJson<{ status: string; reservation: AdminReservationItem }>(
    "/api/admin/reservations/check-in",
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${accessToken}`,
      },
      body: JSON.stringify({
        reservation_id: reservationId,
        qr_code_token: qrCodeToken,
      }),
    },
  );
