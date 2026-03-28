import { useEffect, useState } from "react";
import { Link, useLocation } from "react-router-dom";

type ConfirmationState = {
  attendeeName: string;
  attendeeEmail: string;
  attendeePhone: string;
  reservationId: string;
  eventTitle: string;
  date: string;
  time: string;
  location: string;
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
  const stateFromNavigation = location.state as Partial<ConfirmationState> | null;
  const [showToast, setShowToast] = useState(Boolean(stateFromNavigation));

  const confirmationState: ConfirmationState = {
    ...defaultConfirmationState,
    ...stateFromNavigation,
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
              {confirmationState.attendeeEmail} | {confirmationState.attendeePhone}
            </span>
          </div>

          <div className="confirmation-qr-panel">
            <div className="confirmation-qr-shell" aria-hidden="true" />
            <p>Reservation ID</p>
            <strong>{confirmationState.reservationId}</strong>
          </div>
        </div>
      </article>

      <div className="confirmation-actions">
        <button type="button" className="button-primary confirmation-download">
          Download PDF Ticket
        </button>
        <Link to="/" className="button-secondary confirmation-return">
          Back to Events
        </Link>
      </div>

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
