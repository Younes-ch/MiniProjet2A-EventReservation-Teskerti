import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { fetchPublicEvents, type PublicEvent } from "../lib/eventsClient";

type VenueRow = {
  key: string;
  location: string;
  city: string;
  totalEvents: number;
  upcomingEvents: number;
  featuredEventSlug: string;
  featuredEventTitle: string;
};

const buildVenueRows = (events: PublicEvent[]): VenueRow[] => {
  const venueMap = new Map<string, VenueRow>();

  for (const event of events) {
    const key = `${event.location}@@${event.city}`;
    const existing = venueMap.get(key);

    if (!existing) {
      venueMap.set(key, {
        key,
        location: event.location,
        city: event.city,
        totalEvents: 1,
        upcomingEvents: event.seats_available > 0 ? 1 : 0,
        featuredEventSlug: event.slug,
        featuredEventTitle: event.title,
      });
      continue;
    }

    existing.totalEvents += 1;
    if (event.seats_available > 0) {
      existing.upcomingEvents += 1;
    }
  }

  return [...venueMap.values()].sort((left, right) =>
    `${left.location} ${left.city}`.localeCompare(
      `${right.location} ${right.city}`,
    ),
  );
};

export function VenuesPage() {
  const [events, setEvents] = useState<PublicEvent[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  useEffect(() => {
    let isMounted = true;

    const load = async () => {
      setIsLoading(true);
      setErrorMessage(null);

      try {
        const payload = await fetchPublicEvents();
        if (!isMounted) {
          return;
        }

        setEvents(payload);
      } catch {
        if (!isMounted) {
          return;
        }

        setErrorMessage("Unable to load venues right now.");
      } finally {
        if (isMounted) {
          setIsLoading(false);
        }
      }
    };

    void load();

    return () => {
      isMounted = false;
    };
  }, []);

  const venueRows = useMemo(() => buildVenueRows(events), [events]);

  return (
    <section className="section-block" aria-labelledby="venues-heading">
      <p className="eyebrow">Location directory</p>
      <h1 id="venues-heading">Venues</h1>
      <p className="section-copy">
        Browse all locations currently hosting events.
      </p>

      {isLoading ? (
        <p className="home-api-state" role="status">
          Loading venues...
        </p>
      ) : errorMessage ? (
        <p className="home-api-state home-api-state-error" role="alert">
          {errorMessage}
        </p>
      ) : venueRows.length === 0 ? (
        <p className="home-api-state" role="status">
          No venues available yet.
        </p>
      ) : (
        <div className="ticket-rail">
          {venueRows.map((venue) => (
            <article key={venue.key} className="ticket-card">
              <div className="ticket-visual ticket-tone-cyan">
                <span>{venue.upcomingEvents} open events</span>
              </div>

              <div className="ticket-main">
                <p className="ticket-kicker">{venue.city}</p>
                <h2>{venue.location}</h2>
                <ul className="ticket-meta" aria-label="Venue details">
                  <li>Total events: {venue.totalEvents}</li>
                  <li>Upcoming: {venue.upcomingEvents}</li>
                  <li>Featured: {venue.featuredEventTitle}</li>
                </ul>
                <Link
                  to={`/events/${venue.featuredEventSlug}`}
                  className="button-secondary wide"
                >
                  Open featured event
                </Link>
              </div>
            </article>
          ))}
        </div>
      )}
    </section>
  );
}
