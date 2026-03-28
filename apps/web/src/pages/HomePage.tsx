import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { fetchPublicEvents, type PublicEvent } from "../lib/eventsClient";

const communityMembers = ["AK", "RM", "CN", "+126"];

const toneClassByToken: Record<string, string> = {
  indigo: "home-event-tone-indigo",
  cyan: "home-event-tone-cyan",
  amber: "home-event-tone-amber",
};

const getToneClass = (event: PublicEvent): string =>
  toneClassByToken[event.visual_tone] ?? "home-event-tone-indigo";

const formatEventPrice = (event: PublicEvent): string => {
  try {
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency: event.currency,
      maximumFractionDigits: 2,
    }).format(event.price_amount);
  } catch {
    return `$${event.price_amount.toFixed(2)}`;
  }
};

const buildAvailabilityBadge = (event: PublicEvent): string => {
  if (event.seats_available <= 0) {
    return "Sold out";
  }

  if (event.seats_available <= 15) {
    return `${event.seats_available} seats left`;
  }

  return `${event.seats_available} seats available`;
};

const buildDateBadge = (event: PublicEvent): { month: string; day: string } => {
  const parsedDate = new Date(event.starts_at);

  if (Number.isNaN(parsedDate.getTime())) {
    return {
      month: "TBD",
      day: "--",
    };
  }

  return {
    month: parsedDate.toLocaleString("en-US", { month: "short" }).toUpperCase(),
    day: parsedDate.toLocaleString("en-US", { day: "2-digit" }),
  };
};

export function HomePage() {
  const [events, setEvents] = useState<PublicEvent[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  const loadEvents = useCallback(async () => {
    setIsLoading(true);
    setLoadError(null);

    try {
      const payload = await fetchPublicEvents();
      setEvents(payload);
    } catch {
      setLoadError("Unable to load events right now. Please try again.");
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadEvents();
  }, [loadEvents]);

  const firstEventSlug = events[0]?.slug;
  const exploreTarget = firstEventSlug
    ? `/events/${firstEventSlug}`
    : "/reserve";

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
            <Link
              to={exploreTarget}
              className="button-primary home-explore-link"
            >
              Explore now
            </Link>
            <button type="button" className="button-secondary" disabled>
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
            <p>Live events streamed from the API.</p>
          </div>
          <div className="home-events-controls">
            <button type="button" aria-label="Previous featured events">
              &#8249;
            </button>
            <button type="button" aria-label="Next featured events">
              &#8250;
            </button>
          </div>
        </div>

        {isLoading ? (
          <p className="home-api-state" role="status">
            Loading events...
          </p>
        ) : loadError ? (
          <div className="home-api-state-row">
            <p className="home-api-state home-api-state-error" role="alert">
              {loadError}
            </p>
            <button
              type="button"
              className="button-secondary"
              onClick={() => void loadEvents()}
            >
              Retry
            </button>
          </div>
        ) : events.length > 0 ? (
          <div className="home-event-grid">
            {events.map((event) => {
              const dateBadge = buildDateBadge(event);

              return (
                <article key={event.id} className="home-event-card">
                  <header className={`home-event-media ${getToneClass(event)}`}>
                    <span>{buildAvailabilityBadge(event)}</span>
                    <small>
                      <strong>{dateBadge.month}</strong>
                      {dateBadge.day}
                    </small>
                  </header>
                  <div className="home-event-body">
                    <p>{event.category}</p>
                    <h3>{event.title}</h3>
                    <div>
                      <span>
                        {event.location}, {event.city}
                      </span>
                      <strong>{formatEventPrice(event)}</strong>
                    </div>
                    <Link
                      to={`/events/${event.slug}`}
                      className="home-event-arrow"
                      aria-label={`Open ${event.title}`}
                    >
                      &#8594;
                    </Link>
                  </div>
                </article>
              );
            })}
          </div>
        ) : (
          <p className="home-api-state" role="status">
            No events are available yet.
          </p>
        )}

        <div className="home-events-more">
          <button
            type="button"
            className="button-secondary"
            onClick={() => void loadEvents()}
            disabled={isLoading}
          >
            {isLoading ? "Refreshing..." : "Refresh events"}
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
