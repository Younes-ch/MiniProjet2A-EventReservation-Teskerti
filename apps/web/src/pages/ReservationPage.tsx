import { useState } from "react";

const experienceSignals = [
  {
    title: "Expert Keynotes",
    copy: "Hear from global visionaries leading the charge in sustainable tech and architectural design.",
  },
  {
    title: "Curated Networking",
    copy: "Connect with 500+ peers through our AI-driven matching system for meaningful conversations.",
  },
  {
    title: "Immersive Labs",
    copy: "Hands-on deep dives with interactive demos and guided product sessions.",
  },
  {
    title: "Live Installations",
    copy: "Multi-sensory exhibits blending light, motion, and generative storytelling.",
  },
];

const packageIncludes = [
  ["Refreshments", "Full Catering"],
  ["Materials", "Digital Toolkit"],
  ["Entry", "General Admission"],
];

export function ReservationPage() {
  const [isModalOpen, setModalOpen] = useState(true);

  return (
    <>
      <section className="reservation-hero" aria-labelledby="reservation-title">
        <div className="reservation-hero-copy">
          <p className="eyebrow">Exclusive event</p>
          <h1 id="reservation-title">EventFlow of Dynamic Interactive Experiences</h1>
          <p>
            Join a full-day architecture and innovation summit crafted for builders,
            creators, and operators shaping the next decade.
          </p>
        </div>
      </section>

      <section className="reservation-content">
        <article className="reservation-overview section-block">
          <h2>Experience The Unfolding</h2>
          <p>
            EventFlow is more than just a conference. It is a curated architecture
            journey through the next decade of technology and design. Join industry
            pioneers as we explore the convergence of lucid interfaces, human-centric
            AI, and the evolving landscape of digital experiences.
          </p>
          <p>
            The day is structured as a series of immersive chapters, each set in a
            different sensory zone of The Glass Pavilion. From high-energy keynote
            firesides to tranquil deep-dive labs, every moment is architected for
            maximum engagement and professional growth.
          </p>

          <div className="reservation-signal-grid">
            {experienceSignals.map((signal) => (
              <article key={signal.title} className="reservation-signal-card">
                <h3>{signal.title}</h3>
                <p>{signal.copy}</p>
              </article>
            ))}
          </div>
        </article>

        <aside className="reservation-price-card" aria-label="Ticket package">
          <p className="reservation-price-label">Admission</p>
          <strong>$299.00</strong>
          <ul>
            {packageIncludes.map(([label, value]) => (
              <li key={label}>
                <span>{label}</span>
                <p>{value}</p>
              </li>
            ))}
          </ul>
          <button
            type="button"
            className="button-primary wide"
            onClick={() => setModalOpen(true)}
          >
            Book now
          </button>
          <small>Non-refundable. Limited tickets remaining.</small>
        </aside>
      </section>

      <section className="reservation-location section-block" aria-label="Venue details">
        <div>
          <h2>Venue & Direction</h2>
          <h3>The Glass Pavilion</h3>
          <p>42nd High Line St, Manhattan, NY 10011</p>
          <p>Accessible via A, C, E subway lines at 14th St Station.</p>
        </div>
        <div className="reservation-map" role="img" aria-label="Map preview placeholder" />
      </section>

      {isModalOpen ? (
        <div className="reservation-modal-layer" role="presentation">
          <button
            type="button"
            className="reservation-modal-backdrop"
            onClick={() => setModalOpen(false)}
            aria-label="Close reservation dialog"
          />
          <section
            className="reservation-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="reservation-modal-title"
          >
            <button
              type="button"
              className="reservation-modal-close"
              onClick={() => setModalOpen(false)}
              aria-label="Close"
            >
              x
            </button>

            <h2 id="reservation-modal-title">Secure Your Spot</h2>
            <p>Fill in your details to finalize the reservation for EventFlow.</p>

            <form onSubmit={(event) => event.preventDefault()}>
              <label htmlFor="reservation-name">Full Name</label>
              <input id="reservation-name" type="text" placeholder="John Architect" />

              <label htmlFor="reservation-email">Email Address</label>
              <input id="reservation-email" type="email" placeholder="john@auraevents.com" />

              <label htmlFor="reservation-phone">Phone Number</label>
              <input id="reservation-phone" type="tel" placeholder="+1 (555) 000-0000" />

              <button type="submit" className="button-primary wide">
                Confirm Reservation
              </button>
            </form>

            <small>Secure payment processed by Aura Events</small>
          </section>
        </div>
      ) : null}
    </>
  );
}
