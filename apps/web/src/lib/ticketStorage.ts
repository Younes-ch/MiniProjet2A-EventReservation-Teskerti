export type LatestTicket = {
  reservationId: string;
  attendeeName: string;
  attendeeEmail: string;
  attendeePhone: string;
  eventTitle: string;
  date: string;
  time: string;
  location: string;
  seatLabels: string[];
  qrCodeToken: string;
  ticketDownloadUrl: string;
  calendarDownloadUrl?: string;
};

const TICKET_STORAGE_KEY = "tiskerti.latest.ticket";
const TICKET_HISTORY_STORAGE_KEY = "tiskerti.ticket.history";

const isValidTicket = (value: unknown): value is LatestTicket => {
  if (!value || typeof value !== "object") {
    return false;
  }

  const parsed = value as Partial<LatestTicket>;

  return (
    typeof parsed.reservationId === "string" &&
    typeof parsed.attendeeName === "string" &&
    typeof parsed.attendeeEmail === "string" &&
    typeof parsed.attendeePhone === "string" &&
    typeof parsed.eventTitle === "string" &&
    typeof parsed.date === "string" &&
    typeof parsed.time === "string" &&
    typeof parsed.location === "string" &&
    Array.isArray(parsed.seatLabels) &&
    parsed.seatLabels.every((seatLabel) => typeof seatLabel === "string") &&
    typeof parsed.qrCodeToken === "string" &&
    typeof parsed.ticketDownloadUrl === "string" &&
    (parsed.calendarDownloadUrl === undefined ||
      typeof parsed.calendarDownloadUrl === "string")
  );
};

const normalizeHistory = (value: unknown): LatestTicket[] => {
  if (!Array.isArray(value)) {
    return [];
  }

  return value.filter((ticket) => isValidTicket(ticket));
};

const saveTicketHistory = (tickets: LatestTicket[]): void => {
  localStorage.setItem(TICKET_HISTORY_STORAGE_KEY, JSON.stringify(tickets));
};

export const loadTicketHistory = (): LatestTicket[] => {
  const raw = localStorage.getItem(TICKET_HISTORY_STORAGE_KEY);
  if (!raw) {
    return [];
  }

  try {
    return normalizeHistory(JSON.parse(raw) as unknown);
  } catch {
    return [];
  }
};

export const saveLatestTicket = (ticket: LatestTicket): void => {
  localStorage.setItem(TICKET_STORAGE_KEY, JSON.stringify(ticket));

  const history = loadTicketHistory();
  const deduplicated = history.filter(
    (item) => item.reservationId !== ticket.reservationId,
  );

  saveTicketHistory([ticket, ...deduplicated].slice(0, 20));
};

export const loadLatestTicket = (): LatestTicket | null => {
  const raw = localStorage.getItem(TICKET_STORAGE_KEY);
  if (!raw) {
    return null;
  }

  try {
    const parsed = JSON.parse(raw) as unknown;

    if (!isValidTicket(parsed)) {
      return null;
    }

    return parsed;
  } catch {
    return null;
  }
};

export const clearLatestTicket = (): void => {
  localStorage.removeItem(TICKET_STORAGE_KEY);
};

export const clearTicketHistory = (): void => {
  localStorage.removeItem(TICKET_HISTORY_STORAGE_KEY);
};
