import { useEffect, useState } from "react";
import { Link, useLocation } from "react-router-dom";
import { loadLatestTicket } from "../lib/ticketStorage";

type ConfirmationState = {
  attendeeName: string;
  attendeeEmail: string;
  attendeePhone: string;
  reservationId: string;
  eventTitle: string;
  date: string;
  time: string;
  location: string;
  seatLabels: string[];
  qrCodeToken: string;
  ticketDownloadUrl: string;
};

const defaultConfirmationState: ConfirmationState = {
  attendeeName: "John Architect",
  attendeeEmail: "john@auraevents.com",
  attendeePhone: "+1 (555) 000-0000",
  reservationId: "EF-2944-XF92",
  eventTitle: "The Luminosity Gala 2024",
  date: "October 24, 2024",
  time: "19:00 - 23:00",
  location: "Grand Plaza, NY",
  seatLabels: [],
  qrCodeToken: "",
  ticketDownloadUrl: "",
};

const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL ?? "").replace(
  /\/$/,
  "",
);

const buildTicketDownloadHref = (ticketDownloadUrl: string): string => {
  if (
    ticketDownloadUrl.startsWith("http://") ||
    ticketDownloadUrl.startsWith("https://")
  ) {
    return ticketDownloadUrl;
  }

  return `${API_BASE_URL}${ticketDownloadUrl}`;
};

const reservationMeta = [
  {
    label: "Date",
    key: "date",
  },
  {
    label: "Time",
    key: "time",
  },
  {
    label: "Location",
    key: "location",
  },
] as const;

export function ConfirmationPage() {
  const location = useLocation();
  const stateFromNavigation =
    location.state as Partial<ConfirmationState> | null;
  const latestStoredTicket = loadLatestTicket();
  const [showToast, setShowToast] = useState(Boolean(stateFromNavigation));
  const [downloadErrorMessage, setDownloadErrorMessage] = useState<
    string | null
  >(null);

  const confirmationState: ConfirmationState = {
    ...defaultConfirmationState,
    ...(latestStoredTicket ?? {}),
    ...stateFromNavigation,
  };

  const handleDownloadTicket = () => {
    if (confirmationState.ticketDownloadUrl.trim().length === 0) {
      setDownloadErrorMessage(
        "Ticket download link is unavailable for this reservation.",
      );
      return;
    }

    setDownloadErrorMessage(null);

    window.open(
      buildTicketDownloadHref(confirmationState.ticketDownloadUrl),
      "_blank",
      "noopener,noreferrer",
    );
  };

  useEffect(() => {
    if (!showToast) {
      return;
    }

    const timeoutId = window.setTimeout(() => {
      setShowToast(false);
    }, 2600);

    return () => {
      window.clearTimeout(timeoutId);
    };
  }, [showToast]);

  return (
    <section className="confirmation-page" aria-labelledby="confirmation-title">
      {showToast ? (
        <p className="confirmation-toast" role="status" aria-live="polite">
          Reservation confirmed for {confirmationState.attendeeName}
        </p>
      ) : null}

      <div className="confirmation-brand" aria-label="EventFlow">
        <span className="confirmation-brand-mark" aria-hidden="true">
          *
        </span>
        <strong>EventFlow</strong>
      </div>

      <header className="confirmation-head">
        <h1 id="confirmation-title">Thank you for your reservation!</h1>
        <p>
          Your spot is secured. We&apos;ve curated a special experience just for
          you. Please keep this confirmation handy.
        </p>
      </header>

      <article className="confirmation-ticket" aria-label="Reservation ticket">
        <div className="confirmation-ticket-banner">
          <span>Confirmed</span>
        </div>

        <div className="confirmation-ticket-main">
          <p className="confirmation-kicker">Exclusive Event</p>
          <h2>{confirmationState.eventTitle}</h2>

          <div className="confirmation-meta-grid">
            {reservationMeta.map((item) => (
              <article key={item.key} className="confirmation-meta-card">
                <p>{item.label}</p>
                <strong>{confirmationState[item.key]}</strong>
              </article>
            ))}
          </div>

          <div className="confirmation-attendee">
            <p>Reserved for</p>
            <strong>{confirmationState.attendeeName}</strong>
            <span>
              {confirmationState.attendeeEmail} |{" "}
              {confirmationState.attendeePhone}
            </span>
          </div>

          {confirmationState.seatLabels.length > 0 ? (
            <div className="confirmation-seats">
              <p>Selected Seats</p>
              <div>
                {confirmationState.seatLabels.map((seatLabel) => (
                  <span key={seatLabel}>{seatLabel}</span>
                ))}
              </div>
            </div>
          ) : null}

          <div className="confirmation-qr-panel">
            <div className="confirmation-qr-shell" aria-hidden="true" />
            <p>Reservation ID</p>
            <strong>{confirmationState.reservationId}</strong>
            {confirmationState.qrCodeToken.length > 0 ? (
              <>
                <p>QR Token</p>
                <strong>{confirmationState.qrCodeToken}</strong>
              </>
            ) : null}
          </div>
        </div>
      </article>

      <div className="confirmation-actions">
        <button
          type="button"
          className="button-primary confirmation-download"
          onClick={handleDownloadTicket}
          disabled={confirmationState.ticketDownloadUrl.trim().length === 0}
        >
          Download PDF Ticket
        </button>
        <Link to="/" className="button-secondary confirmation-return">
          Back to Events
        </Link>
      </div>

      {downloadErrorMessage ? (
        <p className="home-api-state home-api-state-error" role="alert">
          {downloadErrorMessage}
        </p>
      ) : null}

      <p className="confirmation-help">
        Need help? Reach out to us at
        <a href="mailto:support@eventflow.com"> support@eventflow.com</a>
      </p>

      <footer className="confirmation-footer" aria-label="Confirmation footer">
        <small>© 2024 EventFlow Technologies. All rights reserved.</small>
        <ul>
          <li>
            <Link to="/">Privacy</Link>
          </li>
          <li>
            <Link to="/">Terms</Link>
          </li>
          <li>
            <Link to="/">Contact</Link>
          </li>
        </ul>
      </footer>
    </section>
  );
}
