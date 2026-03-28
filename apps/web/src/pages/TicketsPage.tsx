import { Link } from "react-router-dom";

const compactTickets = [
  {
    passType: "Aura Premiere",
    title: "The Synthesis Collective",
    date: "Oct 24, 2024 - 7:00 PM",
    venue: "Grand Horizon Hall, NYC",
    seat: "Section A, Row 12, Seat 42",
    passId: "#7782-AX-1",
    status: "Confirmed",
    tone: "ticket-tone-indigo",
  },
  {
    passType: "Aura Lounge",
    title: "Midnight Jazz Sessions",
    date: "Nov 12, 2024 - 10:30 PM",
    venue: "The Velvet Room, Chicago",
    seat: "General Admission - Table 09",
    passId: "#8829-JZ-4",
    status: "Early Bird",
    tone: "ticket-tone-amber",
  },
];

export function TicketsPage() {
  return (
    <section className="section-block tickets-shell">
      <p className="eyebrow">Reservation center</p>
      <h1>My Tickets</h1>
      <p className="section-copy">
        Manage your upcoming experiences and digital passes. This page is static
        in Phase 0 and will connect to reservation APIs in Phase 1.
      </p>

      <div className="ticket-rail">
        {compactTickets.map((ticket) => (
          <article className="ticket-card" key={ticket.passId}>
            <div className={`ticket-visual ${ticket.tone}`}>
              <span>{ticket.status}</span>
            </div>

            <div className="ticket-main">
              <p className="ticket-kicker">{ticket.passType}</p>
              <h2>{ticket.title}</h2>
              <ul className="ticket-meta" aria-label="Ticket details">
                <li>{ticket.date}</li>
                <li>{ticket.venue}</li>
                <li>{ticket.seat}</li>
              </ul>
              <button type="button" className="button-secondary wide">
                Download as PDF
              </button>
            </div>

            <aside className="ticket-code" aria-label="Pass identifier">
              <div className="qr-shell" aria-hidden="true" />
              <p>Pass ID</p>
              <strong>{ticket.passId}</strong>
            </aside>
          </article>
        ))}
      </div>

      <article className="ticket-feature">
        <div className="ticket-visual ticket-tone-cyan">
          <span>VIP Pass</span>
        </div>
        <div className="ticket-main ticket-main-wide">
          <p className="ticket-kicker">Aura Expo</p>
          <h2>Future of Digital Arts</h2>
          <p className="section-copy">
            Includes full access to the VIP lounge, artist meet-and-greet, and
            priority entry to all interactive exhibits.
          </p>
          <ul className="ticket-meta" aria-label="VIP pass details">
            <li>Dec 05 - Dec 08, 2024</li>
            <li>The Nexus Plaza, SF</li>
            <li>All-Access Membership Included</li>
          </ul>
          <div className="hero-actions">
            <button type="button" className="button-primary">
              Download Full Pass
            </button>
            <button type="button" className="button-secondary">
              Add to Apple Wallet
            </button>
          </div>
        </div>
        <aside className="ticket-code" aria-label="Membership identifier">
          <div className="qr-shell" aria-hidden="true" />
          <p>Membership ID</p>
          <strong>VIP-1102-EX</strong>
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
    </section>
  );
}
