import { Link } from "react-router-dom";

const reservationMeta = [
  {
    label: "Date",
    value: "October 24, 2024",
  },
  {
    label: "Time",
    value: "19:00 - 23:00",
  },
  {
    label: "Location",
    value: "Grand Plaza, NY",
  },
];

export function ConfirmationPage() {
  return (
    <section className="confirmation-page" aria-labelledby="confirmation-title">
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
          <h2>The Luminosity Gala 2024</h2>

          <div className="confirmation-meta-grid">
            {reservationMeta.map((item) => (
              <article key={item.label} className="confirmation-meta-card">
                <p>{item.label}</p>
                <strong>{item.value}</strong>
              </article>
            ))}
          </div>

          <div className="confirmation-qr-panel">
            <div className="confirmation-qr-shell" aria-hidden="true" />
            <p>Reservation ID</p>
            <strong>EF-2944-XF92</strong>
          </div>
        </div>
      </article>

      <button type="button" className="button-primary confirmation-download">
        Download PDF Ticket
      </button>

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
