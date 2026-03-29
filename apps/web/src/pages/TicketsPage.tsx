import { useState } from "react";
import { Link } from "react-router-dom";
import {
  loadLatestTicket,
  loadTicketHistory,
  type LatestTicket,
} from "../lib/ticketStorage";

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

export function TicketsPage() {
  const latestTicket = loadLatestTicket();
  const ticketHistory = loadTicketHistory();
  const archivedTickets = ticketHistory.filter(
    (ticket) => ticket.reservationId !== latestTicket?.reservationId,
  );
  const [downloadErrorMessage, setDownloadErrorMessage] = useState<
    string | null
  >(null);

  const handleDownloadTicket = (ticket: LatestTicket) => {
    if (ticket.ticketDownloadUrl.trim().length === 0) {
      setDownloadErrorMessage(
        "No generated ticket is available yet. Reserve an event first.",
      );
      return;
    }

    setDownloadErrorMessage(null);

    window.open(
      buildTicketDownloadHref(ticket.ticketDownloadUrl),
      "_blank",
      "noopener,noreferrer",
    );
  };

  const handleDownloadLatestTicket = () => {
    if (!latestTicket || latestTicket.ticketDownloadUrl.trim().length === 0) {
      setDownloadErrorMessage(
        "No generated ticket is available yet. Reserve an event first.",
      );
      return;
    }

    setDownloadErrorMessage(null);

    window.open(
      buildTicketDownloadHref(latestTicket.ticketDownloadUrl),
      "_blank",
      "noopener,noreferrer",
    );
  };

  return (
    <section className="section-block tickets-shell">
      <p className="eyebrow">Reservation center</p>
      <h1>My Tickets</h1>
      <p className="section-copy">
        Manage your upcoming experiences and digital passes.
      </p>

      {latestTicket ? (
        <article className="ticket-card">
          <div className="ticket-visual ticket-tone-indigo">
            <span>Latest Reservation</span>
          </div>

          <div className="ticket-main">
            <p className="ticket-kicker">Generated Ticket</p>
            <h2>{latestTicket.eventTitle}</h2>
            <ul className="ticket-meta" aria-label="Latest ticket details">
              <li>
                {latestTicket.date} - {latestTicket.time}
              </li>
              <li>{latestTicket.location}</li>
              <li>{latestTicket.attendeeName}</li>
              {latestTicket.seatLabels.length > 0 ? (
                <li>Seats: {latestTicket.seatLabels.join(", ")}</li>
              ) : null}
            </ul>
            <button
              type="button"
              className="button-secondary wide"
              onClick={handleDownloadLatestTicket}
            >
              Download as PDF
            </button>
          </div>

          <aside className="ticket-code" aria-label="Latest pass identifier">
            <div className="qr-shell" aria-hidden="true" />
            <p>Pass ID</p>
            <strong>{latestTicket.reservationId}</strong>
          </aside>
        </article>
      ) : null}

      {archivedTickets.length > 0 ? (
        <div className="ticket-rail">
          {archivedTickets.map((ticket) => (
            <article className="ticket-card" key={ticket.reservationId}>
              <div className="ticket-visual ticket-tone-amber">
                <span>Archived</span>
              </div>

              <div className="ticket-main">
                <p className="ticket-kicker">Ticket</p>
                <h2>{ticket.eventTitle}</h2>
                <ul className="ticket-meta" aria-label="Ticket details">
                  <li>
                    {ticket.date} - {ticket.time}
                  </li>
                  <li>{ticket.location}</li>
                  <li>{ticket.attendeeName}</li>
                  {ticket.seatLabels.length > 0 ? (
                    <li>Seats: {ticket.seatLabels.join(", ")}</li>
                  ) : null}
                </ul>
                <button
                  type="button"
                  className="button-secondary wide"
                  onClick={() => handleDownloadTicket(ticket)}
                >
                  Download as PDF
                </button>
              </div>

              <aside className="ticket-code" aria-label="Pass identifier">
                <div className="qr-shell" aria-hidden="true" />
                <p>Pass ID</p>
                <strong>{ticket.reservationId}</strong>
              </aside>
            </article>
          ))}
        </div>
      ) : (
        <p className="home-api-state" role="status">
          Your ticket history is empty. Reserve an event to generate tickets.
        </p>
      )}

      <article className="ticket-feature">
        <div className="ticket-visual ticket-tone-cyan">
          <span>Ticket Vault</span>
        </div>
        <div className="ticket-main ticket-main-wide">
          <p className="ticket-kicker">Local history</p>
          <h2>All your generated passes, in one place</h2>
          <p className="section-copy">
            Ticket cards are now generated from your real reservation flow, not
            static demo data.
          </p>
          <ul className="ticket-meta" aria-label="Ticket vault details">
            <li>Latest ticket highlighted at the top</li>
            <li>Older reservations shown in archive cards</li>
            <li>PDF download remains available per ticket</li>
          </ul>
          <div className="hero-actions">
            <button
              type="button"
              className="button-primary"
              onClick={handleDownloadLatestTicket}
              disabled={!latestTicket}
            >
              Download Latest Pass
            </button>
            <Link to="/reserve" className="button-secondary">
              Reserve another event
            </Link>
          </div>
        </div>
        <aside className="ticket-code" aria-label="Ticket vault stats">
          <div className="qr-shell" aria-hidden="true" />
          <p>Stored tickets</p>
          <strong>{ticketHistory.length}</strong>
        </aside>
      </article>

      <div className="ticket-support-grid">
        <article className="ticket-support-card">
          <h2>Need assistance with your tickets?</h2>
          <p>
            Our support team is available 24/7 to help with entry requirements,
            ticket transfers, and refund requests.
          </p>
          <Link to="/login">Visit Help Center</Link>
        </article>
        <article className="ticket-support-card ticket-support-verified">
          <h2>Verified Aura Pass</h2>
          <p>
            Every ticket is cryptographically signed for venue security and
            guaranteed entry at check-in.
          </p>
        </article>
      </div>

      {downloadErrorMessage ? (
        <p className="home-api-state home-api-state-error" role="alert">
          {downloadErrorMessage}
        </p>
      ) : null}
    </section>
  );
}
