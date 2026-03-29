export type LatestTicket = {
  reservationId: string;
  attendeeName: string;
  attendeeEmail: string;
  attendeePhone: string;
  eventTitle: string;
  date: string;
  time: string;
  location: string;
  qrCodeToken: string;
  ticketDownloadUrl: string;
};

const TICKET_STORAGE_KEY = "tiskerti.latest.ticket";

export const saveLatestTicket = (ticket: LatestTicket): void => {
  localStorage.setItem(TICKET_STORAGE_KEY, JSON.stringify(ticket));
};

export const loadLatestTicket = (): LatestTicket | null => {
  const raw = localStorage.getItem(TICKET_STORAGE_KEY);
  if (!raw) {
    return null;
  }

  try {
    const parsed = JSON.parse(raw) as LatestTicket;

    if (
      typeof parsed.reservationId !== "string" ||
      typeof parsed.attendeeName !== "string" ||
      typeof parsed.attendeeEmail !== "string" ||
      typeof parsed.attendeePhone !== "string" ||
      typeof parsed.eventTitle !== "string" ||
      typeof parsed.date !== "string" ||
      typeof parsed.time !== "string" ||
      typeof parsed.location !== "string" ||
      typeof parsed.qrCodeToken !== "string" ||
      typeof parsed.ticketDownloadUrl !== "string"
    ) {
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
