import { Link } from "react-router-dom";

type FeaturedEvent = {
  title: string;
  category: string;
  venue: string;
  price: string;
  badge: string;
  month: string;
  day: string;
  toneClass: string;
};

const featuredEvents: FeaturedEvent[] = [
  {
    title: "Midnight Resonance 2.0",
    category: "Electronic Fusion",
    venue: "The Warehouse District",
    price: "$45.00",
    badge: "82 seats available",
    month: "OCT",
    day: "12",
    toneClass: "home-event-tone-indigo",
  },
  {
    title: "Ephemeral Visions Gallery",
    category: "Modern Art",
    venue: "Skyline Atrium",
    price: "$120.00",
    badge: "12 seats left",
    month: "OCT",
    day: "15",
    toneClass: "home-event-tone-cyan",
  },
  {
    title: "Future Loop: AI 2024",
    category: "Tech Summit",
    venue: "Innovation Hub",
    price: "$299.00",
    badge: "Sold out soon",
    month: "OCT",
    day: "18",
    toneClass: "home-event-tone-amber",
  },
];

const communityMembers = ["AK", "RM", "CN", "+126"];

export function HomePage() {
  return (
    <>
      <section className="home-hero" aria-labelledby="home-heading">
        <div className="home-hero-copy">
          <h1 id="home-heading">
            Experience the <span>Best Events</span>
          </h1>
          <p>
            Discover curated experiences from world-class concerts to intimate
            workshops. Your next unforgettable memory starts here.
          </p>
          <div className="home-hero-actions">
            <Link to="/reserve" className="button-primary home-explore-link">
              Explore now
            </Link>
            <button type="button" className="button-secondary">
              How it works
            </button>
          </div>
        </div>

        <aside className="home-hero-visual" aria-label="Featured event visual">
          <div className="home-hero-image" aria-hidden="true" />
          <article className="home-trending-card" aria-label="Trending event">
            <p>Trending now</p>
            <h2>Neon Nights 2024</h2>
            <ul aria-label="Community attending">
              {communityMembers.map((member) => (
                <li key={member}>{member}</li>
              ))}
            </ul>
          </article>
        </aside>
      </section>

      <section className="home-search" aria-label="Event search shell">
        <label>
          Search events
          <input type="text" placeholder="Concerts, tech, art..." />
        </label>
        <label>
          Location
          <select defaultValue="New York, USA">
            <option>New York, USA</option>
            <option>Los Angeles, USA</option>
            <option>Paris, France</option>
          </select>
        </label>
        <label>
          Date range
          <input type="text" placeholder="Select dates" />
        </label>
        <button type="button" className="button-primary home-find-button">
          Find events
        </button>
      </section>

      <section
        className="home-events"
        aria-labelledby="upcoming-events-heading"
      >
        <div className="home-events-head">
          <div>
            <h2 id="upcoming-events-heading">Upcoming experiences</h2>
            <p>Handpicked events happening in your area this week.</p>
          </div>
          <div className="home-events-controls">
            <button type="button">&#8249;</button>
            <button type="button">&#8250;</button>
          </div>
        </div>

        <div className="home-event-grid">
          {featuredEvents.map((event) => (
            <article key={event.title} className="home-event-card">
              <header className={`home-event-media ${event.toneClass}`}>
                <span>{event.badge}</span>
                <small>
                  <strong>{event.month}</strong>
                  {event.day}
                </small>
              </header>
              <div className="home-event-body">
                <p>{event.category}</p>
                <h3>{event.title}</h3>
                <div>
                  <span>{event.venue}</span>
                  <strong>{event.price}</strong>
                </div>
                <button
                  type="button"
                  className="home-event-arrow"
                  aria-label={`Open ${event.title}`}
                >
                  &#8594;
                </button>
              </div>
            </article>
          ))}
        </div>

        <div className="home-events-more">
          <button type="button" className="button-secondary">
            Load more events
          </button>
        </div>
      </section>

      <section className="home-newsletter" aria-labelledby="newsletter-heading">
        <div>
          <h2 id="newsletter-heading">Never miss an unforgettable moment</h2>
          <p>
            Join our weekly newsletter to get early access to tickets and
            members-only event discounts.
          </p>
        </div>
        <form
          className="home-newsletter-form"
          onSubmit={(event) => event.preventDefault()}
        >
          <label htmlFor="newsletter-email" className="sr-only">
            Enter your email
          </label>
          <input
            id="newsletter-email"
            type="email"
            placeholder="Enter your email"
          />
          <button type="submit" className="button-primary">
            Subscribe
          </button>
        </form>
      </section>

      <section
        className="home-footer-shell"
        aria-label="Community and support links"
      >
        <article>
          <h3>Aura Events</h3>
          <p>
            Curating the world&apos;s most premium and unique experiences,
            designed for the curious.
          </p>
        </article>
        <article>
          <h4>Navigation</h4>
          <ul>
            <li>Home</li>
            <li>All events</li>
            <li>Venues</li>
            <li>Pricing</li>
          </ul>
        </article>
        <article>
          <h4>Community</h4>
          <ul>
            <li>Support center</li>
            <li>Community forum</li>
            <li>Partner program</li>
          </ul>
        </article>
        <article>
          <h4>Social</h4>
          <ul className="home-social-row">
            <li>Share</li>
            <li>Team</li>
            <li>Global</li>
          </ul>
        </article>
      </section>
    </>
  );
}
